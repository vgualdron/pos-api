<?php

namespace App\Services;

use App\Models\Station;
use App\Models\User;
use App\Repositories\StationRepository;
use App\Repositories\UserRepository;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StationService
{
    protected StationRepository $repo;
    protected UserRepository $userRepo;

    public function __construct(StationRepository $repo, UserRepository $userRepo)
    {
        $this->repo = $repo;
        $this->userRepo = $userRepo;
    }

    public function all()
    {
        try {
            return $this->repo->all();
        } catch (Exception $e) {
            throw new Exception('Error al obtener estaciones: ' . $e->getMessage());
        }
    }

    public function find($id)
    {
        try {
            return $this->repo->findById($id);
        } catch (Exception $e) {
            throw new Exception("No se encontró la estación con ID {$id}: " . $e->getMessage());
        }
    }

    public function create(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                $payload = $this->normalizeStationPayload($data);
                $station = $this->repo->create($payload);
                if (($payload['type'] ?? Station::TYPE_REGISTER) === Station::TYPE_REGISTER) {
                    $this->provisionStationUser($station);
                }

                return $station->fresh(['place', 'users']);
            });
        } catch (Exception $e) {
            throw new Exception('Error al crear la estación: ' . $e->getMessage());
        }
    }

    public function update($id, array $data)
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                $payload = $this->normalizeStationPayload($data);
                $station = $this->repo->update($id, $payload);
                if (($station->type ?? Station::TYPE_REGISTER) === Station::TYPE_REGISTER) {
                    $this->syncStationUser($station);
                }

                return $station->fresh(['place', 'users']);
            });
        } catch (Exception $e) {
            throw new Exception("Error al actualizar la estación con ID {$id}: " . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $station = Station::with('users')->findOrFail($id);

                foreach ($station->users as $user) {
                    $this->userRepo->delete($user->id);
                }

                return $this->repo->delete($id);
            });
        } catch (Exception $e) {
            throw new Exception("Error al eliminar la estación con ID {$id}: " . $e->getMessage());
        }
    }

    public function findByStatuses(string $statuses)
    {
        try {
            return $this->repo->findByStatuses($statuses);
        } catch (Exception $e) {
            throw new Exception('Error al filtrar estaciones por estado: ' . $e->getMessage());
        }
    }

    public function findByPlace($placeId)
    {
        try {
            return $this->repo->findByPlace($placeId);
        } catch (Exception $e) {
            throw new Exception('Error al filtrar estaciones por sede: ' . $e->getMessage());
        }
    }

    protected function normalizeStationPayload(array $data): array
    {
        $type = strtolower(trim((string) ($data['type'] ?? Station::TYPE_REGISTER)));
        if (! in_array($type, Station::TYPES, true)) {
            throw new Exception('Tipo de estación inválido. Use register o kitchen.');
        }
        $data['type'] = $type;

        if (array_key_exists('printer_ip', $data)) {
            $ip = trim((string) ($data['printer_ip'] ?? ''));
            $data['printer_ip'] = $ip !== '' ? $ip : null;
        }

        if ($type === Station::TYPE_KITCHEN && empty($data['printer_ip'])) {
            // allow null on create if omitted; kitchen should ideally have IP
        }

        return $data;
    }

    protected function provisionStationUser(Station $station): User
    {
        $station->loadMissing('place.company');
        $place = $station->place;

        if (!$place || !$place->company) {
            throw new Exception('La estación no tiene sede o empresa asociada');
        }

        $domain = $this->resolveCompanyDomain($place->company->configuration);
        if (!$domain) {
            throw new Exception('La empresa no tiene dominio configurado. Defínalo en Empresas antes de crear estaciones.');
        }

        $plainPassword = $this->generateStationPassword();
        $email = $this->buildUniqueStationEmail($station->name, $domain, null, $place->name);

        $user = $this->userRepo->create([
            'name' => $station->name,
            'company_id' => $place->company_id,
            'place_id' => $place->id,
            'station_id' => $station->id,
            'status' => 'activo',
            'email' => $email,
            'password' => $plainPassword,
            'roles' => ['CAJA'],
        ]);

        $station->login_password = $plainPassword;
        $station->save();

        return $user;
    }

    protected function syncStationUser(Station $station): void
    {
        $station->loadMissing(['place.company', 'users']);
        $user = $station->users->sortBy('id')->first();

        if (!$user) {
            $this->provisionStationUser($station);

            return;
        }

        $place = $station->place;
        if (!$place || !$place->company) {
            throw new Exception('La estación no tiene sede o empresa asociada');
        }

        $domain = $this->resolveCompanyDomain($place->company->configuration);
        if (!$domain) {
            throw new Exception('La empresa no tiene dominio configurado. Defínalo en Empresas antes de actualizar estaciones.');
        }

        $email = $this->buildUniqueStationEmail($station->name, $domain, $user->id, $place->name);

        $this->userRepo->update($user->id, [
            'name' => $station->name,
            'company_id' => $place->company_id,
            'place_id' => $place->id,
            'station_id' => $station->id,
            'status' => $station->status === 'activo' ? 'activo' : 'inactivo',
            'email' => $email,
            'roles' => ['CAJA'],
        ]);
    }

    protected function resolveCompanyDomain($configuration): ?string
    {
        if (is_string($configuration)) {
            $decoded = json_decode($configuration, true);
            $configuration = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($configuration)) {
            return null;
        }

        $domain = strtolower(trim((string) ($configuration['domain'] ?? '')));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        $domain = explode('/', $domain)[0] ?? '';
        $domain = explode('?', $domain)[0] ?? '';
        $domain = preg_replace('/:\d+$/', '', $domain);

        return $domain !== '' ? $domain : null;
    }

    protected function buildStationEmailLocal(string $name): string
    {
        $placeholder = '__enye__';
        $protected = preg_replace('/[ñÑ]/u', $placeholder, $name);
        $local = Str::lower(Str::ascii((string) $protected));
        $local = str_replace($placeholder, 'ñ', $local);
        $local = preg_replace('/\s+/', '-', $local);
        $local = preg_replace('/[^a-z0-9ñ\-\._]/u', '', $local);
        $local = trim((string) $local, '-._');

        return $local !== '' ? $local : 'estacion';
    }

    protected function buildUniqueStationEmail(
        string $stationName,
        string $domain,
        ?int $ignoreUserId = null,
        ?string $placeName = null
    ): string {
        $stationLocal = $this->buildStationEmailLocal($stationName);
        $placeLocal = $this->buildStationEmailLocal((string) $placeName);
    
        $baseLocal = $placeLocal !== ''
            ? "{$stationLocal}-{$placeLocal}"
            : $stationLocal;
    
        $local = $baseLocal;
        $suffix = 2;
    
        while ($this->emailExists("{$local}@{$domain}", $ignoreUserId)) {
            $local = "{$baseLocal}-{$suffix}";
            $suffix++;
        }
    
        return "{$local}@{$domain}";
    }

    protected function emailExists(string $email, ?int $ignoreUserId = null): bool
    {
        $query = User::where('email', $email);

        if ($ignoreUserId) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return $query->exists();
    }

    protected function generateStationPassword(): string
    {
        return '123456789';
    }
}
