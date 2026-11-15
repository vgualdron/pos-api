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

    protected $appends = [
        'total_cost',
        'lines_count',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    public function getTotalCostAttribute(): float
    {
        return round($this->lines->sum(function ($line) {
            return (float) $line->qty_base * (float) $line->unit_cost;
        }), 2);
    }

    public function getLinesCountAttribute(): int
    {
        return $this->lines->count();
    }
}
