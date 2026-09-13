<?php

namespace App\Repositories;

use App\Models\Station;

class StationRepository
{
    protected function withRelations(): array
    {
        return [
            'services',
            'users' => function ($query) {
                $query->select('id', 'station_id', 'email', 'name', 'status')->orderBy('id');
            },
        ];
    }

    public function all()
    {
        return Station::with($this->withRelations())->orderBy('place_id')->orderBy('name')->get();
    }

    public function findById($id)
    {
        return Station::with($this->withRelations())->findOrFail($id);
    }

    public function findByStatuses(string $status)
    {
        $statuses = array_map('trim', explode(',', $status));

        return Station::with($this->withRelations())
            ->whereIn('status', $statuses)
            ->orderBy('place_id')
            ->orderBy('name')
            ->get();
    }

    public function findByPlace($placeId)
    {
        return Station::with($this->withRelations())
            ->where('place_id', $placeId)
            ->orderBy('name')
            ->get();
    }

    public function create(array $data)
    {
        return Station::create($data)->load($this->withRelations());
    }

    public function update($id, array $data)
    {
        $station = Station::findOrFail($id);
        $station->update($data);

        return $station->fresh(array_merge(['place'], $this->withRelations()));
    }

    public function delete($id)
    {
        return Station::findOrFail($id)->delete();
    }

    public function syncServices($id, array $services)
    {
        $station = Station::findOrFail($id);
        $syncData = [];

        foreach ($services as $service) {
            $canGenerate = (bool) ($service['can_generate'] ?? false);
            $canCall = (bool) ($service['can_call'] ?? false);
            $canDisplay = (bool) ($service['can_display'] ?? false);

            // No vincular servicios sin ninguna capacidad activa.
            if (!$canGenerate && !$canCall && !$canDisplay) {
                continue;
            }

            $syncData[$service['service_id']] = [
                'can_generate' => $canGenerate,
                'can_call' => $canCall,
                'can_display' => $canDisplay,
                'display_order' => (int) ($service['display_order'] ?? 0),
            ];
        }

        $station->services()->sync($syncData);

        return $station->fresh(array_merge(['place'], $this->withRelations()));
    }
}
