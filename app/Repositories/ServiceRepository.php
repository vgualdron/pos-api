<?php

namespace App\Repositories;

use App\Models\Service;
use Illuminate\Support\Facades\DB;

class ServiceRepository
{
    public function all()
    {
        return Service::with(['followUpServices', 'precedingServices'])
            ->orderBy('place_id')
            ->orderBy('order')
            ->orderBy('name')
            ->get();
    }

    public function findById($id)
    {
        return Service::with(['followUpServices', 'precedingServices'])->findOrFail($id);
    }

    public function findByStatuses(string $status)
    {
        $statuses = array_map('trim', explode(',', $status));

        return Service::with(['followUpServices', 'precedingServices'])
            ->whereIn('status', $statuses)
            ->orderBy('place_id')
            ->orderBy('order')
            ->orderBy('name')
            ->get();
    }

    public function findByPlace($placeId)
    {
        return Service::with(['followUpServices', 'precedingServices'])
            ->where('place_id', $placeId)
            ->orderBy('order')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $followUpIds = $this->extractFollowUpIds($data);
            unset($data['follow_up_service_ids'], $data['parent_service_ids']);

            $service = Service::create($data);
            $service->followUpServices()->sync($followUpIds);

            return $service->fresh(['place', 'followUpServices', 'precedingServices']);
        });
    }

    public function update($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $service = Service::findOrFail($id);
            $syncFollowUps = array_key_exists('follow_up_service_ids', $data);
            $followUpIds = $syncFollowUps ? $this->extractFollowUpIds($data) : null;

            unset($data['follow_up_service_ids'], $data['parent_service_ids']);

            $service->update($data);

            if ($followUpIds !== null) {
                $service->followUpServices()->sync($followUpIds);
            }

            return $service->fresh(['place', 'followUpServices', 'precedingServices']);
        });
    }

    public function delete($id)
    {
        return Service::findOrFail($id)->delete();
    }

    /**
     * @return list<int>
     */
    protected function extractFollowUpIds(array $data): array
    {
        $ids = [];
        if (array_key_exists('follow_up_service_ids', $data) && is_array($data['follow_up_service_ids'])) {
            $ids = $data['follow_up_service_ids'];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }
}
