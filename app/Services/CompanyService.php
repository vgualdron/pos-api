<?php
namespace App\Services;

use App\Models\Company;
use App\Repositories\CompanyRepository;
use Exception;

class CompanyService
{
    public const BUSINESS_MODE_RETAIL = 'retail';

    public const BUSINESS_MODE_RESTAURANT = 'restaurant';

    public const BUSINESS_MODES = [
        self::BUSINESS_MODE_RETAIL,
        self::BUSINESS_MODE_RESTAURANT,
    ];

    protected $repo;

    public function __construct(CompanyRepository $repo)
    {
        $this->repo = $repo;
    }

    public function all()
    {
        try {
            return $this->repo->all();
        } catch (Exception $e) {
            throw new Exception("Error al obtener registros: " . $e->getMessage());
        }
    }

    public function find($id)
    {
        try {
            return $this->repo->findById($id);
        } catch (Exception $e) {
            throw new Exception("No se encontró el registro con ID {$id}: " . $e->getMessage());
        }
    }

    public function create(array $data)
    {
        try {
            $data['configuration'] = $this->normalizeConfiguration($data['configuration'] ?? null);

            return $this->repo->create($data);
        } catch (Exception $e) {
            throw new Exception("Error al crear el registro: " . $e->getMessage());
        }
    }

    public function update($id, array $data)
    {
        try {
            if (array_key_exists('configuration', $data)) {
                $existing = $this->repo->findById($id);
                $data['configuration'] = $this->normalizeConfiguration(
                    $data['configuration'],
                    $existing?->configuration
                );
            }

            return $this->repo->update($id, $data);
        } catch (Exception $e) {
            throw new Exception("Error al actualizar el registro con ID {$id}: " . $e->getMessage());
        }
    }

    public static function resolveBusinessMode(?Company $company): string
    {
        $configuration = $company?->configuration;
        if (is_string($configuration)) {
            $decoded = json_decode($configuration, true);
            $configuration = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($configuration)) {
            return self::BUSINESS_MODE_RETAIL;
        }

        $mode = strtolower(trim((string) ($configuration['business_mode'] ?? self::BUSINESS_MODE_RETAIL)));

        return in_array($mode, self::BUSINESS_MODES, true)
            ? $mode
            : self::BUSINESS_MODE_RETAIL;
    }

    /**
     * @param  mixed  $incoming
     * @param  mixed  $existing
     */
    protected function normalizeConfiguration($incoming, $existing = null): array
    {
        $base = $this->configurationToArray($existing);
        $patch = $this->configurationToArray($incoming);
        $merged = array_merge($base, $patch);

        $mode = strtolower(trim((string) ($merged['business_mode'] ?? self::BUSINESS_MODE_RETAIL)));
        if ($mode === '') {
            $mode = self::BUSINESS_MODE_RETAIL;
        }

        if (!in_array($mode, self::BUSINESS_MODES, true)) {
            throw new Exception('Modo de negocio inválido. Use retail o restaurant.');
        }

        $merged['business_mode'] = $mode;

        return $merged;
    }

    /**
     * @param  mixed  $value
     */
    protected function configurationToArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            if (str_starts_with(trim($value), '#')) {
                return ['template_color' => trim($value)];
            }
        }

        return [];
    }

    public function delete($id)
    {
        try {
            return $this->repo->delete($id);
        } catch (Exception $e) {
            throw new Exception("Error al eliminar el registro con ID {$id}: " . $e->getMessage());
        }
    }

    public function findByStatuses(string $statuses)
    {
        try {
            return $this->repo->findByStatuses($statuses);
        } catch (Exception $e) {
            throw new Exception("Error al filtrar registros por estado: " . $e->getMessage());
        }
    }
}
