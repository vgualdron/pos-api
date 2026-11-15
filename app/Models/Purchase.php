<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'place_id',
        'supplier_id',
        'station_id',
        'user_id',
        'purchased_at',
        'supplier_name',
        'notes',
        'status',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
    ];

    protected $with = ['lines', 'supplier'];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }
}
