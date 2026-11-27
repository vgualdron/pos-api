<?php

namespace App\Services;

use App\Models\Product;
use App\Models\RegisterSession;
use App\Models\Sale;
use App\Models\SaleLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PosDashboardService
{
    public function summaryForPlace(int $placeId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $from = $dateFrom
            ? Carbon::parse($dateFrom)->startOfDay()
            : Carbon::today()->startOfDay();
        $to = $dateTo
            ? Carbon::parse($dateTo)->endOfDay()
            : ($dateFrom
                ? Carbon::parse($dateFrom)->endOfDay()
                : Carbon::today()->endOfDay());

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $salesQuery = Sale::query()
            ->where('place_id', $placeId)
            ->whereBetween('sold_at', [$from, $to]);

        $completed = (clone $salesQuery)->whereIn('status', [
            Sale::STATUS_COMPLETED,
            Sale::STATUS_PARTIALLY_RETURNED,
        ]);
        $cancelledCount = (clone $salesQuery)->where('status', Sale::STATUS_CANCELLED)->count();

        $salesCount = (clone $completed)->count();
        $salesTotal = round(
            (float) (clone $completed)->sum(DB::raw('total - COALESCE(returned_total, 0)')),
            2
        );
        $salesProfit = round(
            (float) (clone $completed)->sum(DB::raw('(total - COALESCE(returned_total, 0)) - (cost_total - COALESCE(returned_cost_total, 0))')),
            2
        );
        $discountTotal = round((float) (clone $completed)->sum('discount_amount'), 2);
        $taxTotal = round((float) (clone $completed)->sum('tax_total'), 2);
        $tipsTotal = round((float) (clone $completed)->sum('tip_amount'), 2);

        $byPayment = [
            'cash' => ['count' => 0, 'total' => 0.0],
            'card' => ['count' => 0, 'total' => 0.0],
            'transfer' => ['count' => 0, 'total' => 0.0],
        ];

        $paymentRows = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.place_id', $placeId)
            ->whereIn('sales.status', [Sale::STATUS_COMPLETED, Sale::STATUS_PARTIALLY_RETURNED])
            ->whereBetween('sales.sold_at', [$from, $to])
            ->select(
                'sale_payments.payment_method',
                DB::raw('COUNT(DISTINCT sales.id) as sales_count'),
                DB::raw('SUM(sale_payments.amount) as sales_total')
            )
            ->groupBy('sale_payments.payment_method')
            ->get();

        foreach ($paymentRows as $row) {
            $method = $row->payment_method ?: 'other';
            if (!isset($byPayment[$method])) {
                $byPayment[$method] = ['count' => 0, 'total' => 0.0];
            }
            $byPayment[$method] = [
                'count' => (int) $row->sales_count,
                'total' => round((float) $row->sales_total, 2),
            ];
        }

        $refundRows = DB::table('sale_return_payments')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_payments.sale_return_id')
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->where('sales.place_id', $placeId)
            ->whereBetween('sale_returns.returned_at', [$from, $to])
            ->select(
                'sale_return_payments.payment_method',
                DB::raw('SUM(sale_return_payments.amount) as refund_total')
            )
            ->groupBy('sale_return_payments.payment_method')
            ->get();

        foreach ($refundRows as $row) {
            $method = $row->payment_method ?: 'other';
            if (!isset($byPayment[$method])) {
                $byPayment[$method] = ['count' => 0, 'total' => 0.0];
            }
            $byPayment[$method]['total'] = round(
                (float) $byPayment[$method]['total'] - (float) $row->refund_total,
                2
            );
        }

        $openSessions = RegisterSession::query()
            ->with(['station:id,name,code', 'user:id,name'])
            ->where('place_id', $placeId)
            ->where('status', 'open')
            ->orderByDesc('opened_at')
            ->get();

        $closedSessions = RegisterSession::query()
            ->where('place_id', $placeId)
            ->where('status', 'closed')
            ->whereBetween('closed_at', [$from, $to])
            ->get();

        $topProducts = SaleLine::query()
            ->select(
                'sale_lines.product_id',
                DB::raw('SUM(sale_lines.qty) as qty_sold'),
                DB::raw('SUM(sale_lines.line_total) as sales_total')
            )
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->where('sales.place_id', $placeId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereBetween('sales.sold_at', [$from, $to])
            ->groupBy('sale_lines.product_id')
            ->orderByDesc('sales_total')
            ->limit(8)
            ->get();

        $productMap = Product::query()
            ->whereIn('id', $topProducts->pluck('product_id')->filter()->all())
            ->get(['id', 'name'])
            ->keyBy('id');

        $topProducts = $topProducts->map(fn ($row) => [
            'product_id' => (int) $row->product_id,
            'name' => $productMap[$row->product_id]->name ?? 'Producto',
            'qty_sold' => round((float) $row->qty_sold, 2),
            'sales_total' => round((float) $row->sales_total, 2),
        ])->values()->all();

        $inventory = app(InventoryService::class)->summaryForPlace($placeId);

        $daily = (clone $completed)
            ->select(
                DB::raw('DATE(sold_at) as day'),
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(total) as sales_total')
            )
            ->groupBy(DB::raw('DATE(sold_at)'))
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => $row->day,
                'count' => (int) $row->sales_count,
                'total' => round((float) $row->sales_total, 2),
            ])
            ->all();

        return [
            'place_id' => $placeId,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'sales' => [
                'count' => $salesCount,
                'cancelled_count' => $cancelledCount,
                'total' => $salesTotal,
                'profit' => $salesProfit,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'tips_total' => $tipsTotal,
                'by_payment' => $byPayment,
            ],
            'sessions' => [
                'open_count' => $openSessions->count(),
                'closed_count' => $closedSessions->count(),
                'closed_sales_total' => round(
                    (float) $closedSessions->sum(fn ($row) =>
                        (float) ($row->cash_sales_total ?? 0)
                        + (float) ($row->card_sales_total ?? 0)
                        + (float) ($row->transfer_sales_total ?? 0)
                    ),
                    2
                ),
                'closed_tips_total' => round((float) $closedSessions->sum('tips_total'), 2),
                'difference_cash_total' => round((float) $closedSessions->sum('difference_cash'), 2),
                'open' => $openSessions,
            ],
            'inventory' => [
                'low_stock_count' => (int) ($inventory['low_stock_count'] ?? count($inventory['low_stock'] ?? [])),
                'expiring_count' => (int) ($inventory['expiring_count'] ?? count($inventory['expiring'] ?? [])),
                'expired_count' => (int) ($inventory['expired_count'] ?? count($inventory['expired'] ?? [])),
            ],
            'top_products' => $topProducts,
            'daily' => $daily,
        ];
    }
}
