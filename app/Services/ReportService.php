<?php
namespace App\Services;

use App\Repositories\ReportRepository;
use Exception;

class ReportService
{
    protected $repo;

    public function __construct(ReportRepository $repo)
    {
        $this->repo = $repo;
    }

    public function all($userId)
    {
        try {
            return $this->repo->all($userId);
        } catch (Exception $e) {
            throw new Exception("Error al obtener registros: " . $e->getMessage());
        }
    }

    public function execute($id)
    {
        try {
            $report = $this->repo->findById($id);

            if (!$report) {
                throw new Exception("No se encontró el reporte con ID {$id}");
            }
            return $this->repo->execute($report->sql);
        } catch (Exception $e) {
            throw new Exception("No se encontró el registro con ID {$id}: " . $e->getMessage());
        }
    }
}
