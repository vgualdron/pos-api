<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tax extends Model
{
    public const KIND_IVA = 'iva';
    public const KIND_ICO = 'ico';
    public const KIND_OTHER = 'other';

    public const STATUS_ACTIVE = 'activo';
    public const STATUS_INACTIVE = 'inactivo';

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'rate',
        'kind',
        'is_inclusive',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'rate' => 'float',
        'is_inclusive' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function presentations(): HasMany
    {
        return $this->hasMany(ProductPresentation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function displayName(): string
    {
        return $this->short_name ?: $this->name;
    }
}
