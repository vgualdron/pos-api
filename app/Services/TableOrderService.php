<?php

namespace App\Services;

use App\Models\DiningTable;
use App\Models\ProductPresentation;
use App\Models\Station;
use App\Models\TableOrder;
use App\Models\TableOrderLine;
use App\Support\SalePricing;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TableOrderService
{
    public function catalogForPlace(int $placeId)
    {
        return app(ProductService::class)->catalog($placeId);
    }

    public function openOrderForTable(int $diningTableId, ?int $userId = null): TableOrder
    {
        $table = DiningTable::with('diningZone')->findOrFail($diningTableId);

        $order = TableOrder::query()
            ->where('dining_table_id', $table->id)
            ->where('status', TableOrder::STATUS_OPEN)
            ->with('lines')
            ->first();

        if ($order) {
            return $order;
        }

        return TableOrder::create([
            'place_id' => $table->place_id,
            'dining_table_id' => $table->id,
            'user_id' => $userId,
            'status' => TableOrder::STATUS_OPEN,
            'opened_at' => Carbon::now(),
            'subtotal' => 0,
            'discount_amount' => 0,
            'discount_percent' => null,
            'tax_base' => 0,
            'tax_total' => 0,
            'total' => 0,
        ])->fresh('lines');
    }

    public function getOpenOrderForTable(int $diningTableId): ?TableOrder
    {
        return TableOrder::query()
            ->where('dining_table_id', $diningTableId)
            ->where('status', TableOrder::STATUS_OPEN)
            ->with(['lines.kitchenStation'])
            ->first();
    }

    public function addLine(int $diningTableId, array $data, ?int $userId = null): TableOrder
    {
        return DB::transaction(function () use ($diningTableId, $data, $userId) {
            $table = DiningTable::findOrFail($diningTableId);
            $order = $this->openOrderForTable($diningTableId, $userId);

            $presentationId = (int) ($data['product_presentation_id'] ?? 0);
            $presentation = ProductPresentation::with(['product.kitchenStation', 'tax'])->find($presentationId);
            if (!$presentation || (int) $presentation->product?->place_id !== (int) $table->place_id) {
                throw new Exception('La presentación no pertenece a esta sede.');
            }
            if (($presentation->status ?? 'activo') !== 'activo') {
                throw new Exception('La presentación no está activa.');
            }
            if (($presentation->product?->status ?? 'activo') !== 'activo') {
                throw new Exception('El producto no está activo.');
            }

            $qty = round((float) ($data['qty'] ?? 0), 4);
            if ($qty <= 0) {
                throw new Exception('La cantidad debe ser mayor a 0.');
            }

            $tax = $presentation->tax ?: SalePricing::defaultTax();
            $unitPrice = array_key_exists('unit_price', $data) && $data['unit_price'] !== null && $data['unit_price'] !== ''
                ? round((float) $data['unit_price'], 2)
                : round((float) $presentation->sale_price, 2);

            if ($unitPrice < 0) {
                throw new Exception('El precio no puede ser negativo.');
            }

            $notes = trim((string) ($data['notes'] ?? ''));
            if (mb_strlen($notes) > 500) {
                throw new Exception('La observación no puede superar 500 caracteres.');
            }

            $factor = max(0.0001, (float) ($presentation->factor ?: 1));
            $maxSort = (int) $order->lines()->max('sort_order');

            $kitchenStationId = $presentation->product?->kitchen_station_id
                ? (int) $presentation->product->kitchen_station_id
                : null;

            TableOrderLine::create([
                'table_order_id' => $order->id,
                'product_id' => $presentation->product_id,
                'product_presentation_id' => $presentation->id,
                'kitchen_station_id' => $kitchenStationId,
                'product_name' => $presentation->product?->name ?: 'Producto',
                'presentation_name' => $presentation->name,
                'qty' => $qty,
                'factor' => $factor,
                'qty_base' => round($qty * $factor, 4),
                'unit_price' => $unitPrice,
                'tax_id' => $tax->id,
                'tax_rate' => (float) $tax->rate,
                'tax_name' => $tax->short_name ?: $tax->name,
                'tax_code' => $tax->code,
                'discount_amount' => 0,
                'line_base' => 0,
                'tax_amount' => 0,
                'line_total' => 0,
                'notes' => $notes !== '' ? $notes : null,
                'sort_order' => $maxSort + 1,
            ]);

            $this->recomputeTotals($order->fresh('lines'));
            $this->syncTableServiceState($table, $userId, false);

            return $order->fresh(['lines.kitchenStation', 'diningTable.diningZone']);
        });
    }

    /**
     * Mark pending lines as sent and return print batches grouped by kitchen station IP.
     *
     * @return array{order: TableOrder, batches: array<int, array<string, mixed>>, invoice_printer_ip: ?string}
     */
    public function sendPending(int $diningTableId, ?int $userId = null): array
    {
        return DB::transaction(function () use ($diningTableId, $userId) {
            $table = DiningTable::with('diningZone')->findOrFail($diningTableId);
            $order = $this->getOpenOrderForTable($diningTableId);
            if (! $order) {
                throw new Exception('No hay comanda abierta en esta mesa.');
            }

            $pending = $order->lines()->whereNull('sent_at')->orderBy('sort_order')->orderBy('id')->get();
            if ($pending->isEmpty()) {
                throw new Exception('No hay productos pendientes por enviar.');
            }

            $batchId = (string) Str::uuid();
            $now = Carbon::now();
            foreach ($pending as $line) {
                $line->update([
                    'sent_at' => $now,
                    'send_batch_id' => $batchId,
                ]);
            }

            $order->update([
                'last_sent_at' => $now,
                'user_id' => $userId ?: $order->user_id,
            ]);

            $this->syncTableServiceState($table, $userId, true);

            $stationIds = $pending->pluck('kitchen_station_id')->filter()->unique()->values();
            $stations = Station::query()->whereIn('id', $stationIds)->get()->keyBy('id');

            $batches = [];
            $grouped = $pending->groupBy(fn ($line) => $line->kitchen_station_id ?: 'none');
            foreach ($grouped as $key => $lines) {
                $station = $key === 'none' ? null : $stations->get((int) $key);
                $batches[] = [
                    'station_id' => $station?->id,
                    'station_name' => $station?->name ?: 'Sin estación',
                    'printer_ip' => $station?->printer_ip,
                    'lines' => $lines->values()->map(fn ($line) => [
                        'id' => $line->id,
                        'product_name' => $line->product_name,
                        'presentation_name' => $line->presentation_name,
                        'qty' => (float) $line->qty,
                        'notes' => $line->notes,
                    ])->all(),
                ];
            }

            $fresh = $order->fresh(['lines.kitchenStation', 'diningTable.diningZone']);

            return [
                'order' => $fresh,
                'batches' => $batches,
                'send_batch_id' => $batchId,
                'invoice_printer_ip' => $table->diningZone?->invoice_printer_ip,
            ];
        });
    }

    public function updateLine(int $lineId, array $data, ?int $userId = null): TableOrder
    {
        return DB::transaction(function () use ($lineId, $data, $userId) {
            $line = TableOrderLine::with('order')->findOrFail($lineId);
            $order = $line->order;
            if (!$order || !$order->isOpen()) {
                throw new Exception('La comanda no está abierta.');
            }

            if (array_key_exists('qty', $data)) {
                $qty = round((float) $data['qty'], 4);
                if ($qty <= 0) {
                    throw new Exception('La cantidad debe ser mayor a 0.');
                }
                $line->qty = $qty;
                $line->qty_base = round($qty * (float) $line->factor, 4);
            }

            if (array_key_exists('notes', $data)) {
                $notes = trim((string) ($data['notes'] ?? ''));
                if (mb_strlen($notes) > 500) {
                    throw new Exception('La observación no puede superar 500 caracteres.');
                }
                $line->notes = $notes !== '' ? $notes : null;
            }

            $line->save();
            if ($userId) {
                $order->update(['user_id' => $userId]);
            }

            $this->recomputeTotals($order->fresh('lines'));

            return $order->fresh(['lines.kitchenStation', 'diningTable.diningZone']);
        });
    }

    public function removeLine(int $lineId, ?int $userId = null): TableOrder
    {
        return DB::transaction(function () use ($lineId, $userId) {
            $line = TableOrderLine::with('order')->findOrFail($lineId);
            $order = $line->order;
            if (!$order || !$order->isOpen()) {
                throw new Exception('La comanda no está abierta.');
            }
            if ($line->sent_at) {
                throw new Exception('No se puede quitar un producto ya enviado a cocina.');
            }

            $line->delete();
            if ($userId) {
                $order->update(['user_id' => $userId]);
            }

            $this->recomputeTotals($order->fresh('lines'));

            return $order->fresh(['lines.kitchenStation', 'diningTable.diningZone']);
        });
    }

    public function closeOpenOrdersForTable(int $diningTableId, string $status = TableOrder::STATUS_VOID): void
    {
        TableOrder::query()
            ->where('dining_table_id', $diningTableId)
            ->where('status', TableOrder::STATUS_OPEN)
            ->update([
                'status' => $status,
                'closed_at' => Carbon::now(),
            ]);
    }

    protected function recomputeTotals(TableOrder $order): void
    {
        $lines = $order->lines;
        $lineGrosses = [];
        foreach ($lines as $index => $line) {
            $lineGrosses[$index] = round(((float) $line->qty) * ((float) $line->unit_price), 2);
        }

        $itemsSubtotal = round(array_sum($lineGrosses), 2);
        $discount = round(max(0, min((float) ($order->discount_amount ?? 0), $itemsSubtotal)), 2);
        $allocated = SalePricing::allocateDiscount($lineGrosses, $discount);

        $taxBase = 0.0;
        $taxTotal = 0.0;
        $payable = 0.0;

        foreach ($lines as $index => $line) {
            $lineDiscount = round($allocated[$index] ?? 0, 2);
            $linePayable = round(max(0, $lineGrosses[$index] - $lineDiscount), 2);
            $split = SalePricing::splitGross($linePayable, (float) $line->tax_rate);

            $line->update([
                'discount_amount' => $lineDiscount,
                'line_base' => $split['base'],
                'tax_amount' => $split['tax'],
                'line_total' => $linePayable,
            ]);

            $taxBase = round($taxBase + $split['base'], 2);
            $taxTotal = round($taxTotal + $split['tax'], 2);
            $payable = round($payable + $linePayable, 2);
        }

        $percent = null;
        if ($itemsSubtotal > 0 && $discount > 0) {
            $percent = round(($discount / $itemsSubtotal) * 100, 2);
        }

        $order->update([
            'subtotal' => $itemsSubtotal,
            'discount_amount' => $discount,
            'discount_percent' => $percent,
            'tax_base' => $taxBase,
            'tax_total' => $taxTotal,
            'total' => $payable,
        ]);
    }

    protected function syncTableServiceState(DiningTable $table, ?int $userId, bool $orderTaken): void
    {
        $now = Carbon::now();
        $updates = [];

        if (($table->operational_status ?? DiningTable::OP_FREE) === DiningTable::OP_FREE) {
            $updates['operational_status'] = DiningTable::OP_OCCUPIED;
            $updates['occupied_at'] = $table->occupied_at ?: $now;
        } elseif (!$table->occupied_at) {
            $updates['occupied_at'] = $now;
        }

        if ($orderTaken && !$table->order_taken_at) {
            $updates['order_taken_at'] = $now;
        }

        if ($updates) {
            $table->update($updates);
        }

        if ($userId) {
            TableOrder::query()
                ->where('dining_table_id', $table->id)
                ->where('status', TableOrder::STATUS_OPEN)
                ->update(['user_id' => $userId]);
        }
    }
}
