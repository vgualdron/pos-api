<?php
namespace App\Repositories;

use Illuminate\Support\Facades\Hash;
use App\Models\Role;

class RoleRepository
{
    public function all()
    {
        return Role::with('permissions')->get()->map(function($role) {
            $role->setAttribute('permission_names', $role->permissions->pluck('name')->toArray());
            unset($role->permissions); // Opcional, para no enviar todo el objeto permissions
            return $role;
        });
    }

    public function findById($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        $role->permissions = $role->permissions->pluck('name')->toArray();
        return $role;
    }

    public function findByStatuses(string $status)
    {
        $statuses = array_map('trim', explode(',', $status));
        return Role::whereIn('status', $statuses)->with('permissions')->get()->map(function($role) {
            $role->permissions = $role->permissions->pluck('name')->toArray();
            return $role;
        });
    }

    public function create(array $data)
    {
        $role = Role::create([
            'name' => $data['name'],
            'route' => $data['route'],
        ]);
        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }
        return $role->load('permissions');
    }

    public function update($id, array $data)
    {
        $role = Role::findOrFail($id);
        $role->update($data);
        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }
        return $role->load('permissions');
    }

    public function delete($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();
        return true;
    }
}
