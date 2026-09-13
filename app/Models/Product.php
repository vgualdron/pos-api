<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'place_id',
        'name',
        'sku',
        'barcode',
        'base_unit_name',
        'status',
    ];

    protected $with = ['presentations'];

    protected $appends = ['stock_on_hand'];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function presentations(): HasMany
    {
        return $this->hasMany(ProductPresentation::class)->orderByDesc('is_default')->orderBy('factor');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    public function getStockOnHandAttribute(): float
    {
        if (array_key_exists('lots_sum_qty_remaining', $this->attributes)) {
            return (float) $this->attributes['lots_sum_qty_remaining'];
        }

        if ($this->relationLoaded('lots')) {
            return (float) $this->lots->sum('qty_remaining');
        }

        return (float) $this->lots()->sum('qty_remaining');
    }
}
