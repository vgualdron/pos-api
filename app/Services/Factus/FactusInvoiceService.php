<?php

namespace App\Services\Factus;

use App\Models\CompanyElectronicInvoiceSetting;
use App\Models\Place;
use App\Models\PlaceElectronicInvoiceSetting;
use App\Models\Sale;
use Exception;
use Illuminate\Support\Facades\Log;

class FactusInvoiceService
{
    public function __construct(
        protected FactusClient $client,
        protected FactusBillMapper $mapper
    ) {
    }

    public function emitForSale(Sale $sale): Sale
    {
        $sale->loadMissing(['place', 'customer', 'lines.product', 'lines.presentation', 'payments']);

        $companyId = (int) ($sale->place->company_id ?? 0);
        if (! $companyId) {
            $place = Place::query()->find($sale->place_id);
            $companyId = (int) ($place?->company_id ?? 0);
        }

        $companySettings = CompanyElectronicInvoiceSetting::query()
            ->where('company_id', $companyId)
            ->first();

        if (! $companySettings || ! $companySettings->is_enabled) {
            throw new Exception('La facturación electrónica no está habilitada para esta empresa.');
        }

        $placeSettings = PlaceElectronicInvoiceSetting::query()
            ->where('place_id', $sale->place_id)
            ->first();

        if (! $placeSettings || ! $placeSettings->is_enabled) {
            throw new Exception('La facturación electrónica no está habilitada para esta sede.');
        }

        if (! $placeSettings->numbering_range_id) {
            throw new Exception('La sede no tiene rango de numeración de factura (asigne el rango # en configuración FE).');
        }

        $sale->fe_status = 'pending';
        $sale->fe_reference_code = 'SALE-' . $sale->id;
        $sale->fe_error = null;
        $sale->save();

        try {
            $payload = $this->mapper->toValidatePayload($sale, $placeSettings);
            $response = $this->client->postJson($companySettings, '/v2/bills/validate', $payload);
            $data = $response['data'] ?? $response;

            $sale->fe_status = ! empty($data['cufe']) || ! empty($data['is_validated']) ? 'accepted' : 'submitted';
            $sale->fe_number = $data['number'] ?? null;
            $sale->fe_cufe = $data['cufe'] ?? null;
            $sale->fe_qr_url = $data['links']['qr'] ?? null;
            $sale->fe_public_url = $data['links']['public_url'] ?? null;
            $sale->fe_emitted_at = now();
            $sale->fe_error = null;
            $sale->save();

            return $sale->fresh([
                'lines.lots',
                'lines.presentation',
                'lines.product',
                'payments',
                'station',
                'user',
                'customer',
            ]);
        } catch (Exception $e) {
            Log::warning('Factus bill emit failed', [
                'sale_id' => $sale->id,
                'message' => $e->getMessage(),
            ]);

            $sale->fe_status = 'failed';
            $sale->fe_error = $e->getMessage();
            $sale->save();

            throw new Exception('Venta guardada, pero falló la factura electrónica: ' . $e->getMessage());
        }
    }
}
