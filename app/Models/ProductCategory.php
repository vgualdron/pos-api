<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    protected $fillable = [
        'place_id',
        'name',
        'sort_order',
        'status',
        'show_on_register',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'show_on_register' => 'boolean',
    ];

    protected $attributes = [
        'show_on_register' => true,
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
