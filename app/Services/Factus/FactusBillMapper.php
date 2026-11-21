<?php

namespace App\Services\Factus;

use App\Models\Customer;
use App\Models\PlaceElectronicInvoiceSetting;
use App\Models\Sale;
use App\Support\DianCustomerCatalog;
use Exception;

class FactusBillMapper
{
    public function toValidatePayload(Sale $sale, PlaceElectronicInvoiceSetting $placeSettings): array
    {
        $sale->loadMissing(['customer', 'lines.product', 'lines.presentation', 'payments']);

        $customer = $sale->customer;
        if (! $customer) {
            throw new Exception('La factura electrónica requiere un cliente.');
        }

        if (! $placeSettings->numbering_range_id) {
            throw new Exception('La sede no tiene rango de numeración de factura configurado.');
        }

        $payload = [
            'reference_code' => 'SALE-' . $sale->id,
            'document' => '01',
            'numbering_range_id' => (int) $placeSettings->numbering_range_id,
            'operation_type' => '10',
            'send_email' => false,
            'payment_details' => $this->mapPayments($sale),
            'customer' => $this->mapCustomer($customer),
            'items' => $this->mapItems($sale),
        ];

        $establishment = $this->mapEstablishment($placeSettings);
        if ($establishment) {
            $payload['establishment'] = $establishment;
        }

        return $payload;
    }

    protected function mapCustomer(Customer $customer): array
    {
        $isCompany = $customer->person_type === DianCustomerCatalog::PERSON_JURIDICA
            || $customer->document_type === '31';

        $row = [
            'identification_document_code' => (string) $customer->document_type,
            'identification' => preg_replace('/\D+/', '', (string) $customer->document_number) ?: (string) $customer->document_number,
            'legal_organization_code' => $isCompany ? '1' : '2',
            'tribute_code' => 'ZZ',
            'responsibilities' => $this->mapResponsibilities($customer),
            'address' => $customer->address_line ?: 'No informado',
            'email' => $customer->email ?: null,
            'phone' => $customer->mobile ?: $customer->phone ?: null,
            'country_code' => $customer->country_code ?: 'CO',
            'municipality_code' => $customer->city_code ?: null,
        ];

        if ($customer->verification_digit !== null && $customer->verification_digit !== '') {
            $row['dv'] = (string) $customer->verification_digit;
        }

        if ($isCompany) {
            $row['company'] = $customer->legal_name ?: $customer->display_name;
            $row['trade_name'] = $customer->legal_name ?: null;
        } else {
            $row['names'] = $customer->display_name ?: trim(implode(' ', array_filter([
                $customer->first_name,
                $customer->first_last_name,
                $customer->second_last_name,
            ])));
        }

        return array_filter($row, fn ($v) => $v !== null && $v !== '');
    }

    protected function mapResponsibilities(Customer $customer): array
    {
        $codes = $customer->fiscal_responsibilities;
        if (is_array($codes) && count($codes) > 0) {
            return array_values(array_map('strval', $codes));
        }

        return ['R-99-PN'];
    }

    protected function mapItems(Sale $sale): array
    {
        $items = [];
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
            throw new Exception('La factura no tiene ítems válidos.');
        }

        return $items;
    }

    protected function mapPayments(Sale $sale): array
    {
        $rows = [];
        $payments = $sale->payments;

        if ($payments->isEmpty()) {
            $payments = collect([[
                'payment_method' => $sale->payment_method ?: 'cash',
                'amount' => $sale->total,
            ]]);
        }

        foreach ($payments as $payment) {
            $method = is_array($payment) ? ($payment['payment_method'] ?? 'cash') : $payment->payment_method;
            $amount = is_array($payment) ? ($payment['amount'] ?? 0) : $payment->amount;
            $rows[] = [
                'payment_form' => '1',
                'payment_method_code' => $this->mapPaymentMethodCode($method),
                'amount' => number_format((float) $amount, 2, '.', ''),
            ];
        }

        return $rows;
    }

    protected function mapPaymentMethodCode(string $method): string
    {
        return match ($method) {
            'card' => '48',
            'transfer' => '42',
            default => '10', // cash
        };
    }

    protected function mapTaxCode(?string $internalCode, float $rate): string
    {
        $code = strtoupper((string) $internalCode);
        if (str_starts_with($code, 'ICO') || str_contains($code, 'INC')) {
            return '04';
        }

        // Default IVA
        return '01';
    }

    protected function mapEstablishment(PlaceElectronicInvoiceSetting $settings): ?array
    {
        $name = trim((string) $settings->establishment_name);
        $address = trim((string) $settings->establishment_address);
        $phone = trim((string) $settings->establishment_phone);
        $email = trim((string) $settings->establishment_email);
        $muni = trim((string) $settings->municipality_code);

        if ($name === '' || $address === '' || $phone === '' || $email === '' || $muni === '') {
            return null;
        }

        return [
            'name' => $name,
            'address' => $address,
            'phone_number' => $phone,
            'email' => $email,
            'municipality_code' => $muni,
        ];
    }
}
