<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Station extends Model
{
    public const TYPE_KIOSK = 'kiosk';
    public const TYPE_CONSULTORY = 'consultory';
    public const TYPE_DISPLAY = 'display';
    public const TYPE_REGISTER = 'register';

    public const TYPES = [
        self::TYPE_KIOSK,
        self::TYPE_CONSULTORY,
        self::TYPE_DISPLAY,
        self::TYPE_REGISTER,
    ];

    protected $fillable = [
        'place_id',
        'name',
        'code',
        'type',
        'can_generate_tickets',
        'can_call_tickets',
        'max_open_turns',
        'show_priority_option',
        'issues_super_priority',
        'printer_ip',
        'status',
        'login_password',
    ];

    protected $casts = [
        'can_generate_tickets' => 'boolean',
        'can_call_tickets' => 'boolean',
        'max_open_turns' => 'integer',
        'show_priority_option' => 'boolean',
        'issues_super_priority' => 'boolean',
    ];

    protected $with = ['place'];

    protected $appends = ['login_email'];

    public function getLoginEmailAttribute(): ?string
    {
        if ($this->relationLoaded('users')) {
            return $this->users->sortBy('id')->first()?->email;
        }

        return $this->users()->orderBy('id')->value('email');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'station_services')
            ->withPivot(['can_generate', 'can_call', 'can_display', 'display_order'])
            ->withTimestamps()
            ->orderBy('services.order')
            ->orderBy('services.name');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function issuedTurns(): HasMany
    {
        return $this->hasMany(Turn::class, 'station_id');
    }

    public function calledTurns(): HasMany
    {
        return $this->hasMany(Turn::class, 'called_by_station_id');
    }

    public function maxOpenTurnsLimit(): int
    {
        $value = (int) ($this->max_open_turns ?? 1);

        return max(1, min(50, $value));
    }

    public function issuesSuperPriority(): bool
    {
        return (bool) ($this->issues_super_priority ?? false);
    }
}
