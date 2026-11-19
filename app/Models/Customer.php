<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'place_id',
        'person_type',
        'document_type',
        'document_number',
        'verification_digit',
        'legal_name',
        'first_name',
        'first_last_name',
        'second_last_name',
        'email',
        'phone',
        'mobile',
        'address_line',
        'city_code',
        'city_name',
        'department_code',
        'department_name',
        'country_code',
        'tax_regime',
        'fiscal_responsibilities',
        'merchant_registration',
        'status',
        'notes',
    ];

    protected $casts = [
        'fiscal_responsibilities' => 'array',
    ];

    protected $appends = ['document_label', 'display_name'];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function getDocumentLabelAttribute(): string
    {
        $map = \App\Support\DianCustomerCatalog::DOCUMENT_TYPES;
        $type = $this->document_type;
        $label = $map[$type] ?? $type;
        $number = $this->document_number;
        if ($this->verification_digit !== null && $this->verification_digit !== '') {
            $number .= '-' . $this->verification_digit;
        }

        return trim($label . ' ' . $number);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->legal_name ?: trim(implode(' ', array_filter([
            $this->first_name,
            $this->first_last_name,
            $this->second_last_name,
        ])));
    }
}
