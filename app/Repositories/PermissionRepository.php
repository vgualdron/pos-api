<?php

namespace App\Repositories;

use App\Models\Permission;

class PermissionRepository
{
    public function all()
    {
        return Permission::with('group')->orderBy('order', 'asc')->get();
    }

    public function findById($id)
    {
        return Permission::with('group')->findOrFail($id);
    }

    public function findByStatuses(string $status)
    {
        $statuses = array_map('trim', explode(',', $status));
        return Permission::with('group')->whereIn('status', $statuses)->orderBy('order', 'asc')->get();
    }

    public function create(array $data)
    {
        return Permission::create($data);
    }

    public function update($id, array $data)
    {
        $model = Permission::findOrFail($id);
        return $model->update($data);
    }

    public function delete($id)
    {
        $model = Permission::findOrFail($id);
        return $model->delete();
    }
}
