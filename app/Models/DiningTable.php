<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiningTable extends Model
{
    public const STATUS_ACTIVE = 'activo';

    public const STATUS_INACTIVE = 'inactivo';

    public const OP_FREE = 'free';

    public const OP_OCCUPIED = 'occupied';

    public const OP_BILLING = 'billing';

    public const OPERATIONAL_STATUSES = [
        self::OP_FREE,
        self::OP_OCCUPIED,
        self::OP_BILLING,
    ];

    protected $fillable = [
        'place_id',
        'code',
        'name',
        'seats',
        'dining_zone_id',
        'operational_status',
        'occupied_at',
        'order_taken_at',
        'status',
        'sort_order',
    ];

    protected $with = ['diningZone'];

    protected $casts = [
        'seats' => 'integer',
        'sort_order' => 'integer',
        'occupied_at' => 'datetime',
        'order_taken_at' => 'datetime',
    ];

    protected $attributes = [
        'operational_status' => self::OP_FREE,
        'status' => self::STATUS_ACTIVE,
        'sort_order' => 0,
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function diningZone(): BelongsTo
    {
        return $this->belongsTo(DiningZone::class);
    }

    public function isActive(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }
}
