<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Station extends Model
{
    public const TYPE_REGISTER = 'register';

    public const TYPES = [
        self::TYPE_REGISTER,
    ];

    protected $fillable = [
        'place_id',
        'name',
        'code',
        'type',
        'status',
        'login_password',
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

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
