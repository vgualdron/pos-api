<?php

namespace App\Support;

use App\Models\User;
use Exception;
use Illuminate\Support\Collection;

class CompanyScope
{
    public static function canSeeAll(User $user, bool $allowUsersAll = false): bool
    {
        if ($user->can('companies.allCompanies')) {
            return true;
        }

        return $allowUsersAll && $user->can('users.allUsers');
    }

    public static function assert(User $user, int $companyId, bool $allowUsersAll = false): void
    {
        if (! $companyId) {
            throw new Exception('Debe indicar una empresa.');
        }

        if (self::canSeeAll($user, $allowUsersAll)) {
            return;
        }

        if ($user->company_id && (int) $user->company_id === (int) $companyId) {
            return;
        }

        throw new Exception('No tiene permiso para ver o gestionar esta empresa.');
    }

    public static function restrict(User $user, Collection $companies, bool $allowUsersAll = false): Collection
    {
        if (self::canSeeAll($user, $allowUsersAll)) {
            return $companies->values();
        }

        if (! $user->company_id) {
            return collect();
        }

        return $companies->where('id', (int) $user->company_id)->values();
    }
}
