<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturnLine extends Model
{
    protected $fillable = [
        'sale_return_id',
        'sale_line_id',
        'qty',
        'factor',
        'qty_base',
        'unit_price',
        'tax_id',
        'tax_rate',
        'tax_name',
        'tax_code',
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

    protected $with = ['lots'];

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(SaleReturnLineLot::class);
    }
}
