<?php

namespace App\Services;

use App\Models\Customer;
use App\Support\DianCustomerCatalog;
use Exception;

class CustomerService
{
    public function byPlace(int $placeId, bool $onlyActive = false, ?string $search = null)
    {
        $query = Customer::query()
            ->where('place_id', $placeId)
            ->orderBy('legal_name');

        if ($onlyActive) {
            $query->where('status', 'activo');
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('legal_name', 'like', $term)
                    ->orWhere('document_number', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('mobile', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('first_last_name', 'like', $term);
            });
        }

        return $query->limit(500)->get();
    }

    public function find(int $id): Customer
    {
        return Customer::findOrFail($id);
    }

    public function findByDocument(int $placeId, string $documentType, string $documentNumber): ?Customer
    {
        return Customer::query()
            ->where('place_id', $placeId)
            ->where('document_type', $documentType)
            ->where('document_number', $this->normalizeDocumentNumber($documentNumber))
            ->first();
    }

    public function create(array $data): Customer
    {
        $payload = $this->normalizePayload($data, true);

        $exists = $this->findByDocument(
            (int) $payload['place_id'],
            $payload['document_type'],
            $payload['document_number']
        );
        if ($exists) {
            throw new Exception('Ya existe un cliente con ese documento en esta sede.');
        }

        return Customer::create($payload);
    }

    public function update(int $id, array $data): Customer
    {
        $customer = $this->find($id);
        $payload = $this->normalizePayload(array_merge($customer->toArray(), $data), false);
        unset($payload['place_id']);

        if (
            isset($payload['document_type'], $payload['document_number'])
            && (
                $payload['document_type'] !== $customer->document_type
                || $payload['document_number'] !== $customer->document_number
            )
        ) {
            $exists = $this->findByDocument(
                (int) $customer->place_id,
                $payload['document_type'],
                $payload['document_number']
            );
            if ($exists && (int) $exists->id !== (int) $customer->id) {
                throw new Exception('Ya existe un cliente con ese documento en esta sede.');
            }
        }

        $customer->update($payload);

        return $customer->fresh();
    }

    public function delete(int $id): void
    {
        $customer = $this->find($id);
        if ($customer->sales()->exists()) {
            throw new Exception('No se puede eliminar un cliente con ventas. Márquelo inactivo.');
        }
        $customer->delete();
    }

    protected function normalizePayload(array $data, bool $creating): array
    {
        $personType = $data['person_type'] ?? DianCustomerCatalog::PERSON_NATURAL;
        if (!DianCustomerCatalog::isValidPersonType($personType)) {
            throw new Exception('Tipo de persona inválido.');
        }

        $documentType = (string) ($data['document_type'] ?? '');
        if (!DianCustomerCatalog::isValidDocumentType($documentType)) {
            throw new Exception('Tipo de documento DIAN inválido.');
        }

        $documentNumber = $this->normalizeDocumentNumber($data['document_number'] ?? '');
        if ($documentNumber === '') {
            throw new Exception('El número de documento es obligatorio.');
        }

        $taxRegime = $data['tax_regime'] ?? null;
        if (!DianCustomerCatalog::isValidTaxRegime($taxRegime)) {
            throw new Exception('Régimen fiscal inválido.');
        }

        $firstName = $this->nullableString($data['first_name'] ?? null);
        $firstLastName = $this->nullableString($data['first_last_name'] ?? null);
        $secondLastName = $this->nullableString($data['second_last_name'] ?? null);
        $legalName = trim((string) ($data['legal_name'] ?? ''));

        if ($personType === DianCustomerCatalog::PERSON_NATURAL) {
            if ($legalName === '') {
                $legalName = trim(implode(' ', array_filter([$firstName, $firstLastName, $secondLastName])));
            }
            if ($legalName === '') {
                throw new Exception('Indique el nombre del cliente.');
            }
        } elseif ($legalName === '') {
            throw new Exception('La razón social es obligatoria.');
        }

        if ($documentType === '31' && ($data['verification_digit'] ?? '') === '' && $creating) {
            // DV recommended for NIT; allow null but keep empty as null
        }

        $responsibilities = $data['fiscal_responsibilities'] ?? null;
        if (is_string($responsibilities)) {
            $decoded = json_decode($responsibilities, true);
            $responsibilities = is_array($decoded) ? $decoded : null;
        }
        if ($responsibilities !== null && !is_array($responsibilities)) {
            throw new Exception('Las responsabilidades fiscales deben ser una lista.');
        }

        $payload = [
            'person_type' => $personType,
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'verification_digit' => $this->nullableString($data['verification_digit'] ?? null),
            'legal_name' => $legalName,
            'first_name' => $firstName,
            'first_last_name' => $firstLastName,
            'second_last_name' => $secondLastName,
            'email' => $this->nullableString($data['email'] ?? null),
            'phone' => $this->nullableString($data['phone'] ?? null),
            'mobile' => $this->nullableString($data['mobile'] ?? null),
            'address_line' => $this->nullableString($data['address_line'] ?? null),
            'city_code' => $this->nullableString($data['city_code'] ?? null),
            'city_name' => $this->nullableString($data['city_name'] ?? null),
            'department_code' => $this->nullableString($data['department_code'] ?? null),
            'department_name' => $this->nullableString($data['department_name'] ?? null),
            'country_code' => strtoupper($this->nullableString($data['country_code'] ?? 'CO') ?: 'CO'),
            'tax_regime' => $this->nullableString($taxRegime),
            'fiscal_responsibilities' => $responsibilities,
            'merchant_registration' => $this->nullableString($data['merchant_registration'] ?? null),
            'status' => ($data['status'] ?? 'activo') === 'inactivo' ? 'inactivo' : 'activo',
            'notes' => $this->nullableString($data['notes'] ?? null),
        ];

        if ($creating || array_key_exists('place_id', $data)) {
            $payload['place_id'] = (int) ($data['place_id'] ?? 0);
            if ($payload['place_id'] <= 0) {
                throw new Exception('La sede es obligatoria.');
            }
        }

        return $payload;
    }

    protected function normalizeDocumentNumber($value): string
    {
        return preg_replace('/\s+/', '', trim((string) $value)) ?? '';
    }

    protected function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
