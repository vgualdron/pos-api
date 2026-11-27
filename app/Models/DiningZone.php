<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningZone extends Model
{
    public const STATUS_ACTIVE = 'activo';

    public const STATUS_INACTIVE = 'inactivo';

    protected $fillable = [
        'place_id',
        'name',
        'sort_order',
        'invoice_printer_ip',
        'status',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'status' => self::STATUS_ACTIVE,
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }
}
