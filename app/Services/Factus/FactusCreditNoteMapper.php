<?php

namespace App\Services\Factus;

use App\Models\PlaceElectronicInvoiceSetting;
use App\Models\Sale;
use App\Models\SaleReturn;
use Exception;

class FactusCreditNoteMapper extends FactusBillMapper
{
    /**
     * @param  array<int, object|array{payment_method?:string,amount?:float|int|string}>  $refundPayments
     */
    public function toValidatePayloadForReturn(
        Sale $sale,
        SaleReturn $saleReturn,
        PlaceElectronicInvoiceSetting $placeSettings,
        array $refundPayments = [],
        string $correctionConceptCode = '1'
    ): array {
        $sale->loadMissing(['customer', 'lines.product', 'lines.presentation']);
        $saleReturn->loadMissing(['lines.saleLine.product', 'lines.saleLine.presentation', 'payments']);

        if (! $sale->fe_number) {
            throw new Exception('La venta no tiene número de factura electrónica para referenciar la nota crédito.');
        }

        if (! $placeSettings->credit_note_numbering_range_id) {
            throw new Exception('La sede no tiene rango de numeración de nota crédito (asigne el rango # 22 en configuración FE).');
        }

        $customer = $sale->customer;
        if (! $customer) {
            throw new Exception('La nota crédito requiere el cliente de la factura.');
        }

        $payload = [
            'reference_code' => 'RETURN-' . $saleReturn->id,
            'correction_concept_code' => $correctionConceptCode,
            'customization_id' => '20',
            'bill_number' => (string) $sale->fe_number,
            'numbering_range_id' => (int) $placeSettings->credit_note_numbering_range_id,
            'observation' => $this->truncateObservation(
                $saleReturn->reason ?: ('Devolución venta #' . $sale->id)
            ),
            'payment_details' => $this->mapRefundPayments($refundPayments, (float) $saleReturn->total),
            'customer' => $this->mapCustomer($customer),
            'items' => $this->mapReturnItems($saleReturn),
        ];

        $establishment = $this->mapEstablishment($placeSettings);
        if ($establishment) {
            $payload['establishment'] = $establishment;
        }

        return $payload;
    }

    public function toValidatePayloadForCancel(
        Sale $sale,
        PlaceElectronicInvoiceSetting $placeSettings,
        ?string $reason = null
    ): array {
        $sale->loadMissing(['customer', 'lines.product', 'lines.presentation', 'payments']);

        if (! $sale->fe_number) {
            throw new Exception('La venta no tiene número de factura electrónica para referenciar la nota crédito.');
        }

        if (! $placeSettings->credit_note_numbering_range_id) {
            throw new Exception('La sede no tiene rango de numeración de nota crédito (asigne el rango # 22 en configuración FE).');
        }

        $customer = $sale->customer;
        if (! $customer) {
            throw new Exception('La nota crédito requiere el cliente de la factura.');
        }

        $payload = [
            'reference_code' => 'CANCEL-' . $sale->id,
            'correction_concept_code' => '2',
            'customization_id' => '20',
            'bill_number' => (string) $sale->fe_number,
            'numbering_range_id' => (int) $placeSettings->credit_note_numbering_range_id,
            'observation' => $this->truncateObservation($reason ?: ('Anulación venta #' . $sale->id)),
            'payment_details' => $this->mapPayments($sale),
            'customer' => $this->mapCustomer($customer),
            'items' => $this->mapCancelItems($sale),
        ];

        $establishment = $this->mapEstablishment($placeSettings);
        if ($establishment) {
            $payload['establishment'] = $establishment;
        }

        return $payload;
    }

    protected function mapReturnItems(SaleReturn $saleReturn): array
    {
        $items = [];
        $note = $this->truncateObservation($saleReturn->reason ?: ('Devolución #' . $saleReturn->id));

        foreach ($saleReturn->lines as $returnLine) {
            $saleLine = $returnLine->saleLine;
            if (! $saleLine) {
                continue;
            }

            $qty = (float) $returnLine->qty;
            if ($qty <= 0) {
                continue;
            }

            $lineBase = (float) ($returnLine->line_base ?? 0);
            $unitNet = $qty > 0 ? round($lineBase / $qty, 2) : 0.0;
            $taxCode = $this->mapTaxCode($returnLine->tax_code ?? $saleLine->tax_code, (float) ($returnLine->tax_rate ?? 0));
            $taxRate = number_format((float) ($returnLine->tax_rate ?? 0), 2, '.', '');

            $tax = [
                'code' => $taxCode,
                'rate' => $taxRate,
            ];
            if ((float) ($returnLine->tax_rate ?? 0) <= 0) {
                $tax['is_excluded'] = true;
            }

            $product = $saleLine->product;
            $presentation = $saleLine->presentation;
            $sku = $presentation?->barcode
                ?: ($product?->sku ?: ('P' . $saleLine->product_id . '-' . $saleLine->product_presentation_id));

            $items[] = [
                'note' => $note,
                'code_reference' => (string) $sku,
                'name' => ($product?->name ?: 'Producto') . ($presentation?->name ? ' · ' . $presentation->name : ''),
                'quantity' => number_format($qty, 2, '.', ''),
                'discount_rate' => '0.00',
                'price' => number_format($unitNet, 2, '.', ''),
                'unit_measure_code' => '94',
                'standard_code' => '999',
                'taxes' => [$tax],
            ];
        }

        if (count($items) === 0) {
            throw new Exception('La nota crédito no tiene ítems válidos.');
        }

        return $items;
    }

    protected function mapCancelItems(Sale $sale): array
    {
        $items = [];
        $note = 'Anulación venta #' . $sale->id;

        foreach ($sale->lines as $line) {
            $qty = (float) $line->qty;
            if ($qty <= 0) {
                continue;
            }

            $lineBase = (float) ($line->line_base ?? 0);
            $unitNet = $qty > 0 ? round($lineBase / $qty, 2) : 0.0;
            $taxCode = $this->mapTaxCode($line->tax_code, (float) $line->tax_rate);
            $taxRate = number_format((float) $line->tax_rate, 2, '.', '');

            $tax = [
                'code' => $taxCode,
                'rate' => $taxRate,
            ];
            if ((float) $line->tax_rate <= 0) {
                $tax['is_excluded'] = true;
            }

            $sku = $line->presentation->barcode
                ?: ($line->product->sku ?: ('P' . $line->product_id . '-' . $line->product_presentation_id));

            $items[] = [
                'note' => $note,
                'code_reference' => (string) $sku,
                'name' => $line->product->name . ($line->presentation->name ? ' · ' . $line->presentation->name : ''),
                'quantity' => number_format($qty, 2, '.', ''),
                'discount_rate' => '0.00',
                'price' => number_format($unitNet, 2, '.', ''),
                'unit_measure_code' => '94',
                'standard_code' => '999',
                'taxes' => [$tax],
            ];
        }

        if (count($items) === 0) {
            throw new Exception('La nota crédito de anulación no tiene ítems válidos.');
        }

        return $items;
    }

    /**
     * @param  array<int, object|array{payment_method?:string,amount?:float|int|string}>  $payments
     */
    protected function mapRefundPayments(array $payments, float $fallbackTotal): array
    {
        $rows = [];
        foreach ($payments as $payment) {
            $method = is_array($payment) ? ($payment['payment_method'] ?? 'cash') : ($payment->payment_method ?? 'cash');
            $amount = is_array($payment) ? ($payment['amount'] ?? 0) : ($payment->amount ?? 0);
            if ((float) $amount <= 0) {
                continue;
            }
            $rows[] = [
                'payment_form' => '1',
                'payment_method_code' => $this->mapPaymentMethodCode((string) $method),
                'amount' => number_format((float) $amount, 2, '.', ''),
            ];
        }

        if (! $rows) {
            $rows[] = [
                'payment_form' => '1',
                'payment_method_code' => '10',
                'amount' => number_format(max(0, $fallbackTotal), 2, '.', ''),
            ];
        }

        return $rows;
    }

    protected function truncateObservation(?string $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            $text = 'Nota crédito';
        }

        return mb_substr($text, 0, 500);
    }
}
