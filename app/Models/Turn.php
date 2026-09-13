<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turn extends Model
{
    public const STATUS_WAITING = 'waiting';
    public const STATUS_CALLED = 'called';
    public const STATUS_RECALLED = 'recalled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_SUPER = 'super';
    public const PRIORITY_GENERAL = 'priority';
    public const PRIORITY_ELDERLY = 'elderly';
    public const PRIORITY_PREGNANT = 'pregnant';
    public const PRIORITY_REDUCED_MOBILITY = 'reduced_mobility';

    /** Valores permitidos al crear/actualizar turnos */
    public const PRIORITIES = [
        self::PRIORITY_NORMAL,
        self::PRIORITY_SUPER,
        self::PRIORITY_GENERAL,
        self::PRIORITY_ELDERLY,
        self::PRIORITY_PREGNANT,
        self::PRIORITY_REDUCED_MOBILITY,
    ];

    /** Orden de atención en cola (primero = más prioritario) */
    public const PRIORITY_QUEUE_ORDER = [
        self::PRIORITY_SUPER,
        self::PRIORITY_ELDERLY,
        self::PRIORITY_PREGNANT,
        self::PRIORITY_REDUCED_MOBILITY,
        self::PRIORITY_GENERAL,
        self::PRIORITY_NORMAL,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_CALLED,
        self::STATUS_RECALLED,
        self::STATUS_IN_PROGRESS,
    ];

    public static function isPriorityTurn(?string $priority): bool
    {
        return filled($priority) && $priority !== self::PRIORITY_NORMAL;
    }

    public static function priorityQueueFieldSql(): string
    {
        $list = collect(self::PRIORITY_QUEUE_ORDER)
            ->map(fn (string $value) => "'" . str_replace("'", "\\'", $value) . "'")
            ->implode(', ');

        return "FIELD(priority, {$list})";
    }

    public static function priorityQueueValueSql(): string
    {
        $list = collect(self::PRIORITY_QUEUE_ORDER)
            ->map(fn (string $value) => "'" . str_replace("'", "\\'", $value) . "'")
            ->implode(', ');

        return "FIELD(?, {$list})";
    }

    public static function ticketPrefixOf(?string $ticketNumber): string
    {
        $ticketNumber = strtoupper(trim((string) $ticketNumber));
        if ($ticketNumber === '') {
            return '';
        }

        $pos = strrpos($ticketNumber, '-');

        return $pos === false ? $ticketNumber : substr($ticketNumber, 0, $pos);
    }

    public static function ticketPrefixFor(string $basePrefix, ?string $priority): string
    {
        $base = trim($basePrefix);
        if ($priority === self::PRIORITY_SUPER) {
            return str_starts_with(strtoupper($base), 'X') ? $base : 'X'.$base;
        }
        if (self::isPriorityTurn($priority)) {
            return str_ends_with(strtoupper($base), 'P') ? $base : $base.'P';
        }

        return $base;
    }

    protected $fillable = [
        'place_id',
        'service_id',
        'station_id',
        'called_by_station_id',
        'parent_turn_id',
        'ticket_number',
        'daily_sequence',
        'patient_name',
        'patient_document',
        'patient_phone',
        'patient_company',
        'priority',
        'status',
        'qr_token',
        'called_at',
        'silent_call',
        'started_at',
        'completed_at',
        'no_show_note',
        'no_show_station_id',
        'rating',
        'rating_comment',
        'rated_at',
    ];

    protected $casts = [
        'called_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'rated_at' => 'datetime',
        'silent_call' => 'boolean',
    ];

    protected $with = ['service', 'station', 'calledByStation', 'parentTurn'];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function calledByStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'called_by_station_id');
    }

    public function noShowStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'no_show_station_id');
    }

    public function parentTurn(): BelongsTo
    {
        return $this->belongsTo(Turn::class, 'parent_turn_id');
    }

    public function childTurns(): HasMany
    {
        return $this->hasMany(Turn::class, 'parent_turn_id');
    }
}
