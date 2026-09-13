<?php

namespace App\Repositories;

use App\Models\Place;

class PlaceRepository
{
    public function all()
    {
        return Place::with(['company'])->orderBy('company_id', 'asc')->orderBy('name', 'asc')->get();
    }

    public function findById($id)
    {
        return Place::with(['company'])->findOrFail($id);
    }

    public function findByStatuses(string $status)
    {
        $statuses = array_map('trim', explode(',', $status));
        return Place::with(['company'])->whereIn('status', $statuses)->orderBy('name', 'asc')->get();
    }

    public function findByCompany($idCompany)
    {
        return Place::with(['company'])->where('company_id', $idCompany)->orderBy('name', 'asc')->get();
    }

    public function create(array $data)
    {
        $place = Place::create($data);
        return $place->load(['company']);
    }


    public function update($id, array $data)
    {
        $place = Place::findOrFail($id);

        $place->update($data);
        return $place->load(['company']);
    }


    public function delete($id)
    {
        $model = Place::findOrFail($id);
        return $model->delete();
    }
}
