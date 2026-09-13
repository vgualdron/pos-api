<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisplayAlert extends Model
{
    public const STATUS_ACTIVE = 'activo';
    public const STATUS_INACTIVE = 'inactivo';

    public const TYPE_INFO = 'info';
    public const TYPE_WARNING = 'warning';
    public const TYPE_EMERGENCY = 'emergency';

    public const TYPES = [
        self::TYPE_INFO,
        self::TYPE_WARNING,
        self::TYPE_EMERGENCY,
    ];

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    protected $fillable = [
        'place_id',
        'name',
        'message',
        'type',
        'status',
        'display_order',
        'duration_seconds',
        'repeat_count',
        'speak_enabled',
        'show_on_screen',
        'created_by',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'duration_seconds' => 'integer',
        'repeat_count' => 'integer',
        'speak_enabled' => 'boolean',
        'show_on_screen' => 'boolean',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
