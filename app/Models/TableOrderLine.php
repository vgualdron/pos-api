<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableOrderLine extends Model
{
    protected $fillable = [
        'table_order_id',
        'product_id',
        'product_presentation_id',
        'product_name',
        'presentation_name',
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
        'notes',
        'sort_order',
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
        'sort_order' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(TableOrder::class, 'table_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->without(['presentations']);
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'product_presentation_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
