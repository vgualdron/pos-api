<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnPriorityPreload extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_USED = 'used';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'place_id',
        'scheduled_date',
        'patient_document',
        'patient_name',
        'patient_phone',
        'patient_company',
        'priority',
        'status',
        'used_at',
        'used_turn_id',
        'created_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date:Y-m-d',
        'used_at' => 'datetime',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function usedTurn(): BelongsTo
    {
        return $this->belongsTo(Turn::class, 'used_turn_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
