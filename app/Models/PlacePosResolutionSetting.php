<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlacePosResolutionSetting extends Model
{
    protected $fillable = [
        'place_id',
        'is_enabled',
        'resolution_number',
        'resolution_date',
        'valid_from',
        'valid_to',
        'prefix',
        'range_from',
        'range_to',
        'current_number',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'resolution_date' => 'date',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'range_from' => 'integer',
        'range_to' => 'integer',
        'current_number' => 'integer',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
