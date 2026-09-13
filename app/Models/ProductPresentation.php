<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPresentation extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'factor',
        'sale_price',
        'barcode',
        'is_default',
        'status',
    ];

    protected $casts = [
        'factor' => 'float',
        'sale_price' => 'float',
        'is_default' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
