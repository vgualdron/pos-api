<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    public const STATUS_ACTIVE = 'activo';

    /** @deprecated Usar Turn::PRIORITIES */
    public const PRIORITIES = Turn::PRIORITIES;

    protected $fillable = [
        'place_id',
        'name',
        'code',
        'prefix',
        'description',
        'estimated_minutes',
        'order',
        'status',
    ];

    protected $with = ['place'];

    protected $appends = ['follow_up_service_ids', 'parent_service_ids'];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * Servicios que este servicio puede generar después (configurado en el inicial).
     */
    public function followUpServices(): BelongsToMany
    {
        return $this->belongsToMany(
            Service::class,
            'service_follow_ups',
            'service_id',
            'follow_up_service_id'
        )->withTimestamps();
    }

    /**
     * Servicios iniciales que listan a este como generable después.
     */
    public function precedingServices(): BelongsToMany
    {
        return $this->belongsToMany(
            Service::class,
            'service_follow_ups',
            'follow_up_service_id',
            'service_id'
        )->withTimestamps();
    }

    public function stations(): BelongsToMany
    {
        return $this->belongsToMany(Station::class, 'station_services')
            ->withPivot(['can_generate', 'can_call', 'can_display', 'display_order'])
            ->withTimestamps();
    }

    public function turns(): HasMany
    {
        return $this->hasMany(Turn::class);
    }

    /**
     * @return list<int>
     */
    public function getFollowUpServiceIdsAttribute(): array
    {
        if ($this->relationLoaded('followUpServices')) {
            return $this->followUpServices->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        }

        return $this->followUpServices()->pluck('services.id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Padres / servicios iniciales válidos para generar este.
     *
     * @return list<int>
     */
    public function getParentServiceIdsAttribute(): array
    {
        if ($this->relationLoaded('precedingServices')) {
            return $this->precedingServices->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        }

        return $this->precedingServices()->pluck('services.id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Ya no se exige padre para generar: aparecer en "siguientes" es opcional.
     */
    public function requiresParent(): bool
    {
        return false;
    }
}
