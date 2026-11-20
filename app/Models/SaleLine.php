<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleLine extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'product_presentation_id',
        'qty',
        'factor',
        'qty_base',
        'unit_price',
        'tax_rate',
        'discount_amount',
        'line_base',
        'tax_amount',
        'line_total',
        'cost_total',
    ];

    protected $casts = [
        'qty' => 'float',
        'factor' => 'float',
        'qty_base' => 'float',
        'unit_price' => 'float',
        'tax_rate' => 'float',
        'discount_amount' => 'float',
        'line_base' => 'float',
        'tax_amount' => 'float',
        'line_total' => 'float',
        'cost_total' => 'float',
    ];

    protected $appends = ['profit'];

    protected $with = ['lots', 'presentation', 'product'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->without(['presentations']);
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'product_presentation_id');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(SaleLineLot::class);
    }

    public function getProfitAttribute(): float
    {
        return round(((float) $this->line_total) - ((float) $this->cost_total), 2);
    }
}
