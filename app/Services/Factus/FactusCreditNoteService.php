<?php

namespace App\Services\Factus;

use App\Models\CompanyElectronicInvoiceSetting;
use App\Models\Place;
use App\Models\PlaceElectronicInvoiceSetting;
use App\Models\Sale;
use App\Models\SaleReturn;
use Exception;
use Illuminate\Support\Facades\Log;

class FactusCreditNoteService
{
    public function __construct(
        protected FactusClient $client,
        protected FactusCreditNoteMapper $mapper
    ) {
    }

    public function emitForReturn(Sale $sale, SaleReturn $saleReturn): SaleReturn
    {
        if (! $this->shouldEmitForSale($sale)) {
            return $saleReturn;
        }

        $saleReturn->dian_status = 'pending';
        $saleReturn->nc_reference_code = 'RETURN-' . $saleReturn->id;
        $saleReturn->nc_error = null;
        $saleReturn->save();

        try {
            [$companySettings, $placeSettings] = $this->loadSettings($sale);
            $payload = $this->mapper->toValidatePayloadForReturn(
                $sale,
                $saleReturn,
                $placeSettings,
                $saleReturn->payments->all(),
                '1'
            );
            $response = $this->client->postJson($companySettings, '/v2/credit-notes/validate', $payload);
            $data = $response['data'] ?? $response;

            $this->applySuccessToReturn($saleReturn, $data);

            return $saleReturn->fresh(['lines', 'payments']);
        } catch (Exception $e) {
            Log::warning('Factus credit note (return) emit failed', [
                'sale_id' => $sale->id,
                'sale_return_id' => $saleReturn->id,
                'message' => $e->getMessage(),
            ]);

            $saleReturn->dian_status = 'failed';
            $saleReturn->nc_error = $e->getMessage();
            $saleReturn->save();

            return $saleReturn->fresh(['lines', 'payments']);
        }
    }

    public function emitForCancel(Sale $sale, ?string $reason = null): Sale
    {
        if (! $this->shouldEmitForSale($sale)) {
            return $sale;
        }

        $sale->nc_status = 'pending';
        $sale->nc_reference_code = 'CANCEL-' . $sale->id;
        $sale->nc_error = null;
        $sale->save();

        try {
            [$companySettings, $placeSettings] = $this->loadSettings($sale);
            $payload = $this->mapper->toValidatePayloadForCancel($sale, $placeSettings, $reason);
            $response = $this->client->postJson($companySettings, '/v2/credit-notes/validate', $payload);
            $data = $response['data'] ?? $response;

            $sale->nc_status = ! empty($data['cude']) || ! empty($data['cufe']) || ! empty($data['is_validated'])
                ? 'accepted'
                : 'submitted';
            $sale->nc_number = $data['number'] ?? null;
            $sale->nc_cude = $data['cude'] ?? ($data['cufe'] ?? null);
            $sale->nc_qr_url = $data['links']['qr'] ?? null;
            $sale->nc_public_url = $data['links']['public_url'] ?? null;
            $sale->nc_emitted_at = now();
            $sale->nc_error = null;
            $sale->save();

            return $sale;
        } catch (Exception $e) {
            Log::warning('Factus credit note (cancel) emit failed', [
                'sale_id' => $sale->id,
                'message' => $e->getMessage(),
            ]);

            $sale->nc_status = 'failed';
            $sale->nc_error = $e->getMessage();
            $sale->save();

            return $sale;
        }
    }

    public function retryForReturn(Sale $sale, SaleReturn $saleReturn): SaleReturn
    {
        if (! $this->shouldEmitForSale($sale)) {
            throw new Exception('Esta venta no admite nota crédito electrónica.');
        }

        if ((int) $saleReturn->sale_id !== (int) $sale->id) {
            throw new Exception('La devolución no pertenece a esta venta.');
        }

        if (! empty($saleReturn->nc_cude) || ($saleReturn->dian_status ?? null) === 'accepted') {
            throw new Exception('Esta devolución ya tiene nota crédito aceptada.');
        }

        $status = $saleReturn->dian_status ?? null;
        if (! in_array($status, ['failed', 'pending', 'submitted', null, ''], true)) {
            throw new Exception('Esta devolución no admite reintento de nota crédito.');
        }

        return $this->emitForReturn($sale, $saleReturn);
    }

    public function retryForCancel(Sale $sale): Sale
    {
        if (! $this->shouldEmitForSale($sale)) {
            throw new Exception('Esta venta no admite nota crédito electrónica.');
        }

        if (($sale->status ?? null) !== Sale::STATUS_CANCELLED) {
            throw new Exception('Solo se puede reintentar la NC de anulación en una venta anulada.');
        }

        if (! empty($sale->nc_cude) || ($sale->nc_status ?? null) === 'accepted') {
            throw new Exception('Esta anulación ya tiene nota crédito aceptada.');
        }

        $status = $sale->nc_status ?? null;
        if (! in_array($status, ['failed', 'pending', 'submitted', null, ''], true)) {
            throw new Exception('Esta anulación no admite reintento de nota crédito.');
        }

        return $this->emitForCancel($sale, $sale->cancel_reason);
    }

    protected function shouldEmitForSale(Sale $sale): bool
    {
        if (($sale->document_type ?? null) !== Sale::DOCUMENT_ELECTRONIC_INVOICE) {
            return false;
        }

        if (empty($sale->fe_number)) {
            return false;
        }

        $status = $sale->fe_status ?? null;
        if (! in_array($status, ['accepted', 'submitted'], true) && empty($sale->fe_cufe)) {
            return false;
        }

        return true;
    }

    /**
     * @return array{0:CompanyElectronicInvoiceSetting,1:PlaceElectronicInvoiceSetting}
     */
    protected function loadSettings(Sale $sale): array
    {
        $sale->loadMissing(['place']);

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

        if (! $placeSettings->credit_note_numbering_range_id) {
            throw new Exception('La sede no tiene rango de nota crédito configurado (documento 22).');
        }

        return [$companySettings, $placeSettings];
    }

    protected function applySuccessToReturn(SaleReturn $saleReturn, array $data): void
    {
        $accepted = ! empty($data['cude']) || ! empty($data['cufe']) || ! empty($data['is_validated']);
        $saleReturn->dian_status = $accepted ? 'accepted' : 'submitted';
        $saleReturn->dian_credit_note_id = $data['number'] ?? ($data['id'] ?? null);
        $saleReturn->nc_number = $data['number'] ?? null;
        $saleReturn->nc_cude = $data['cude'] ?? ($data['cufe'] ?? null);
        $saleReturn->nc_qr_url = $data['links']['qr'] ?? null;
        $saleReturn->nc_public_url = $data['links']['public_url'] ?? null;
        $saleReturn->nc_emitted_at = now();
        $saleReturn->nc_error = null;
        $saleReturn->save();
    }
}
