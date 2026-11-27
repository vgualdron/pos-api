<?php
namespace App\Services;

use App\Models\Station;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    protected UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function login(string $email, string $password): array
    {
        Artisan::call('permission:cache-reset');
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('optimize:clear');

        $user = $this->userRepository->findByEmail($email);

        if (!$user || !Hash::check($password, $user->password)) {
            throw new \Exception('Credenciales incorrectas');
        }

        $user->load(['company', 'place', 'station.place']);

        if ($user->status !== 'activo') {
            throw new \Exception('Usuario inactivo o suspendido');
        }

        $user->tokens()->delete(); // si quito esto, permito multiples sesiones de cada usuario

        $token = $user->createToken('api-token')->plainTextToken;

        $permissions = $user->getPermissionsViaRoles()->unique('id')->values();
        $grouped = $permissions
                    ->sortBy('order')
                    ->groupBy('group')
                    ->map(function ($groupPermissions) {
                        $g = $groupPermissions->first()->group;

                        return [
                            'name' => $g->name,
                            'icon' => $g->icon,
                            'label' => $g->label,
                            'description' => $g->description,
                            'order' => $g->order,
                            'options' => $groupPermissions
                                ->sortBy('order')
                                ->unique('subgroup')
                                ->map(function ($perm) {
                                    return [
                                        'name' => $perm->subgroup,
                                        'route' => $perm->route,
                                        'icon' => $perm->icon,
                                        'order' => $perm->order,
                                    ];
                                })
                                ->values()
                        ];
                    })
                    ->sortBy('order') // orden final por orden de grupo
                    ->values(); // convertir a colección indexada


        $stationContext = $this->buildStationContext($user->station);
        $businessMode = CompanyService::resolveBusinessMode($user->company);

        return [
            'user' => $user,
            'token' => $token,
            'permissions' => $permissions,
            'roles' => $user->getRoleNames(),
            'grouped' => $grouped,
            'station' => $stationContext['station'],
            'ui_mode' => $stationContext['ui_mode'],
            'business_mode' => $businessMode,
        ];
    }

    public function context(User $user): array
    {
        $user->load(['company', 'place', 'station.place']);

        return array_merge($this->buildStationContext($user->station), [
            'business_mode' => CompanyService::resolveBusinessMode($user->company),
        ]);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw new \Exception('La contraseña actual no es correcta');
        }

        $user->password = bcrypt($newPassword);
        $user->save();
    }

    protected function buildStationContext(?Station $station): array
    {
        if (!$station) {
            return [
                'station' => null,
                'ui_mode' => null,
            ];
        }

        return [
            'station' => $station,
            'ui_mode' => $station->type === Station::TYPE_REGISTER ? 'register' : null,
        ];
    }
}
