<?php

namespace App\Repositories;

use App\Models\DisplayAlert;

class DisplayAlertRepository
{
    public function findById(int $id): DisplayAlert
    {
        return DisplayAlert::with('place')->findOrFail($id);
    }

    public function listByPlace(int $placeId)
    {
        return DisplayAlert::where('place_id', $placeId)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    public function nextDisplayOrder(int $placeId): int
    {
        return ((int) DisplayAlert::where('place_id', $placeId)->max('display_order')) + 1;
    }

    public function create(array $data): DisplayAlert
    {
        return DisplayAlert::create($data)->fresh('place');
    }

    public function update(DisplayAlert $record, array $data): DisplayAlert
    {
        $record->update($data);

        return $record->fresh('place');
    }

    public function delete(DisplayAlert $record): void
    {
        $record->delete();
    }
}
