<?php

namespace App\Repositories;

use App\Models\TurnPriorityPreload;

class TurnPriorityPreloadRepository
{
    public function findById(int $id): TurnPriorityPreload
    {
        return TurnPriorityPreload::with('place')->findOrFail($id);
    }

    public function listByPlaceAndDate(int $placeId, string $date)
    {
        return TurnPriorityPreload::where('place_id', $placeId)
            ->whereDate('scheduled_date', $date)
            ->orderBy('patient_name')
            ->get();
    }

    public function findPendingMatch(int $placeId, string $document, string $date): ?TurnPriorityPreload
    {
        $normalized = $this->normalizeDocument($document);

        return TurnPriorityPreload::where('place_id', $placeId)
            ->whereDate('scheduled_date', $date)
            ->where('status', TurnPriorityPreload::STATUS_PENDING)
            ->where('patient_document', $normalized)
            ->first();
    }

    public function create(array $data): TurnPriorityPreload
    {
        return TurnPriorityPreload::create($data);
    }

    public function update(TurnPriorityPreload $record, array $data): TurnPriorityPreload
    {
        $record->update($data);

        return $record->fresh('place');
    }

    public function delete(TurnPriorityPreload $record): void
    {
        $record->delete();
    }

    public function normalizeDocument(?string $document): string
    {
        return preg_replace('/\D+/', '', (string) $document) ?: '';
    }
}
