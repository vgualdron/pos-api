<?php

namespace App\Repositories;

use App\Models\Station;

class StationRepository
{
    protected function withRelations(): array
    {
        return [
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
}
