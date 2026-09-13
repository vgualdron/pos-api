<?php

namespace App\Repositories;

use App\Models\Group;

class GroupRepository
{
    public function all()
    {
        return Group::where('id','>', 0)->orderBy('order', 'asc')->get();
    }

    public function findById($id)
    {
        return Group::findOrFail($id);
    }

    public function findByStatuses(string $status)
    {
        $statuses = array_map('trim', explode(',', $status));
        return Group::whereIn('status', $statuses)->orderBy('order', 'asc')->get();
    }

    public function create(array $data)
    {
        return Group::create($data);
    }

    public function update($id, array $data)
    {
        $group = Group::findOrFail($id);
        return $group->update($data);
    }

    public function delete($id)
    {
        $group = Group::findOrFail($id);
        return $group->delete();
    }
}
