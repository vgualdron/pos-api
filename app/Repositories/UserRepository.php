<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserRepository
{
    public function all()
    {
        return User::with(['company', 'place'])->get()->map(function($user) {
            $user->setAttribute('role_names', $user->roles->pluck('name')->toArray());
            unset($user->roles);
            return $user;
        });
    }

    public function findById($id)
    {
        return User::with(['company', 'place'])->findOrFail($id);
    }

    public function findByStatuses(string $status)
    {
        $statuses = array_filter(array_map('trim', explode(',', $status)));

        if (empty($statuses)) {
            return collect();
        }

        return User::with(['company', 'place'])
            ->whereIn('users.status', $statuses)
            ->orderBy('users.name', 'asc')
            ->select('users.*')
            ->get()
            ->map(function ($user) {
                $user->setAttribute('role_names', $user->roles->pluck('name')->toArray());
                unset($user->roles);
                return $user;
            });
    }

    public function findByPlace($idPlace)
    {
        return User::with(['company', 'place'])->where('place_id', $idPlace)->orderBy('name', 'asc')->get()->map(function($user) {
            $user->setAttribute('role_names', $user->roles->pluck('name')->toArray());
            unset($user->roles);
            return $user;
        });
    }

    public function findByCompany($idCompany, $userId = null)
    {
        return User::with(['company', 'place'])
            ->where('users.company_id', $idCompany)
            ->where('users.status', 'activo')
            ->orderBy('users.name', 'asc')
            ->select(['users.*'])
            ->get()
            ->map(function($user) {
                $user->setAttribute('role_names', $user->roles->pluck('name')->toArray());
                unset($user->roles);
                return $user;
            });
    }

    public function findByRoleName($roleName)
    {
        return User::with([
                'company',
                'place',
                'roles:id,name'
            ])
            ->role($roleName)
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($user) {
                $user->setAttribute('role_names', $user->roles->pluck('name')->toArray());
                unset($user->roles);
                return $user;
            });
    }

    public function create(array $data)
    {
        $user = User::create([
            'name' => $data['name'],
            'company_id' => $data['company_id'],
            'place_id' => $data['place_id'],
            'station_id' => $data['station_id'] ?? null,
            'status' => $data['status'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $user->load(['roles']);
    }

    public function update($id, array $data)
    {
        $model = User::findOrFail($id);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        $model->update($data);

        if ($roles !== null) {
            $model->syncRoles($roles);
        }

        return $model->load(['roles']);
    }

    public function delete($id)
    {
        $model = User::findOrFail($id);
        return $model->delete();
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function updatePassword($userId, $newPassword)
    {
        return User::where('id', $userId)->update([
            'password' => Hash::make($newPassword)
        ]);
    }

}
