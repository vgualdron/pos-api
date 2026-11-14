<?php

namespace App\Services;

use App\Models\RegisterSession;
use App\Models\Sale;
use App\Support\PosStation;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class RegisterSessionService
{
    public function current($user, ?int $stationId = null): ?RegisterSession
    {
        $station = PosStation::tryResolve($user, $stationId);
        if (!$station) {
            return null;
        }

        return RegisterSession::where('station_id', $station->id)
            ->where('status', RegisterSession::STATUS_OPEN)
            ->latest('id')
            ->first();
    }

    public function summary($user, ?int $stationId = null): array
    {
        $session = $this->current($user, $stationId);
        if (!$session) {
            throw new Exception('No hay una caja abierta.');
        }

        return $this->buildSummary($session);
    }

    public function open($user, array $data): RegisterSession
    {
        $stationId = isset($data['station_id']) ? (int) $data['station_id'] : null;
        $station = PosStation::resolve($user, $stationId);
        $existing = $this->current($user, $station->id);
        if ($existing) {
            return $existing;
        }

        return RegisterSession::create([
            'place_id' => $station->place_id,
            'station_id' => $station->id,
            'user_id' => $user->id,
            'opened_at' => Carbon::now(),
            'opening_cash' => (float) ($data['opening_cash'] ?? 0),
            'status' => RegisterSession::STATUS_OPEN,
        ]);
    }

    public function close($user, array $data): RegisterSession
    {
        return DB::transaction(function () use ($user, $data) {
            $stationId = isset($data['station_id']) ? (int) $data['station_id'] : null;
            $open = $this->current($user, $stationId);
            if (!$open) {
                throw new Exception('No hay una caja abierta.');
            }

            if (!array_key_exists('closing_cash', $data) || $data['closing_cash'] === null || $data['closing_cash'] === '') {
                throw new Exception('Indique el efectivo contado al cerrar la caja.');
            }

            $closingCash = (float) $data['closing_cash'];
            if ($closingCash < 0) {
                throw new Exception('El efectivo contado no puede ser negativo.');
            }

            $summary = $this->buildSummary($open);
            $expected = $summary['expected_cash'];
            $difference = round($closingCash - $expected, 2);

            $open->update([
                'closed_at' => Carbon::now(),
                'closing_cash' => round($closingCash, 2),
                'expected_cash' => $expected,
                'cash_sales_total' => $summary['cash_sales_total'],
                'card_sales_total' => $summary['card_sales_total'],
                'transfer_sales_total' => $summary['transfer_sales_total'],
                'sales_count' => $summary['sales_count'],
                'difference_cash' => $difference,
                'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
                'status' => RegisterSession::STATUS_CLOSED,
            ]);

            return $open->fresh();
        });
    }

    protected function buildSummary(RegisterSession $session): array
    {
        $sales = Sale::where('register_session_id', $session->id)
            ->where('status', Sale::STATUS_COMPLETED)
            ->get();

        $cash = 0.0;
        $card = 0.0;
        $transfer = 0.0;

        foreach ($sales as $sale) {
            $total = (float) $sale->total;
            if ($sale->payment_method === Sale::PAYMENT_CARD) {
                $card += $total;
            } elseif ($sale->payment_method === Sale::PAYMENT_TRANSFER) {
                $transfer += $total;
            } else {
                $cash += $total;
            }
        }

        $opening = (float) $session->opening_cash;
        $expected = round($opening + $cash, 2);

        return [
            'session' => $session,
            'opening_cash' => round($opening, 2),
            'cash_sales_total' => round($cash, 2),
            'card_sales_total' => round($card, 2),
            'transfer_sales_total' => round($transfer, 2),
            'sales_count' => $sales->count(),
            'sales_total' => round($cash + $card + $transfer, 2),
            'expected_cash' => $expected,
        ];
    }
}
