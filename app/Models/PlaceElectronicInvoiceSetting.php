<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceElectronicInvoiceSetting extends Model
{
    protected $fillable = [
        'place_id',
        'is_enabled',
        'numbering_range_id',
        'numbering_range_label',
        'establishment_name',
        'establishment_address',
        'establishment_phone',
        'establishment_email',
        'municipality_code',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'numbering_range_id' => 'integer',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
