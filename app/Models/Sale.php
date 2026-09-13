<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'place_id',
        'station_id',
        'user_id',
        'register_session_id',
        'sold_at',
        'subtotal',
        'total',
        'cost_total',
        'status',
    ];

    protected $casts = [
        'sold_at' => 'datetime',
        'subtotal' => 'float',
        'total' => 'float',
        'cost_total' => 'float',
    ];

    protected $appends = ['profit'];

    protected $with = ['lines'];

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function getProfitAttribute(): float
    {
        return round(((float) $this->total) - ((float) $this->cost_total), 2);
    }
}
