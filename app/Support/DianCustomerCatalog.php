<?php

namespace App\Support;

class DianCustomerCatalog
{
    public const PERSON_NATURAL = 'natural';
    public const PERSON_JURIDICA = 'juridica';

    public const PERSON_TYPES = [
        self::PERSON_NATURAL,
        self::PERSON_JURIDICA,
    ];

    /** DIAN identification type codes commonly used for buyers. */
    public const DOCUMENT_TYPES = [
        '13' => 'Cédula de ciudadanía',
        '31' => 'NIT',
        '22' => 'Cédula de extranjería',
        '21' => 'Tarjeta de extranjería',
        '41' => 'Pasaporte',
        '12' => 'Tarjeta de identidad',
        '11' => 'Registro civil',
        '47' => 'PEP',
        '50' => 'NIT de otro país',
        '91' => 'NUIP',
    ];

    public const TAX_REGIMES = [
        'responsable_iva' => 'Responsable de IVA',
        'no_responsable_iva' => 'No responsable de IVA',
        'simple' => 'Régimen simple de tributación',
    ];

    public const FISCAL_RESPONSIBILITIES = [
        'O-13' => 'Gran contribuyente',
        'O-15' => 'Autorretenedor',
        'O-23' => 'Agente de retención IVA',
        'O-47' => 'Régimen simple de tributación',
        'R-99-PN' => 'No aplica — Otros',
    ];

    public static function isValidDocumentType(?string $code): bool
    {
        return $code !== null && array_key_exists($code, self::DOCUMENT_TYPES);
    }

    public static function isValidPersonType(?string $type): bool
    {
        return in_array($type, self::PERSON_TYPES, true);
    }

    public static function isValidTaxRegime(?string $regime): bool
    {
        if ($regime === null || $regime === '') {
            return true;
        }

        return array_key_exists($regime, self::TAX_REGIMES);
    }
}
