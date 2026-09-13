<?php

namespace App\Services;

use App\Repositories\ServiceRepository;
use Exception;

class ServiceCatalogService
{
    protected ServiceRepository $repo;

    public function __construct(ServiceRepository $repo)
    {
        $this->repo = $repo;
    }

    public function all()
    {
        try {
            return $this->repo->all();
        } catch (Exception $e) {
            throw new Exception('Error al obtener servicios: ' . $e->getMessage());
        }
    }

    public function find($id)
    {
        try {
            return $this->repo->findById($id);
        } catch (Exception $e) {
            throw new Exception("No se encontró el servicio con ID {$id}: " . $e->getMessage());
        }
    }

    public function create(array $data)
    {
        try {
            return $this->repo->create($data);
        } catch (Exception $e) {
            throw new Exception('Error al crear el servicio: ' . $e->getMessage());
        }
    }

    public function update($id, array $data)
    {
        try {
            return $this->repo->update($id, $data);
        } catch (Exception $e) {
            throw new Exception("Error al actualizar el servicio con ID {$id}: " . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            return $this->repo->delete($id);
        } catch (Exception $e) {
            throw new Exception("Error al eliminar el servicio con ID {$id}: " . $e->getMessage());
        }
    }

    public function findByStatuses(string $statuses)
    {
        try {
            return $this->repo->findByStatuses($statuses);
        } catch (Exception $e) {
            throw new Exception('Error al filtrar servicios por estado: ' . $e->getMessage());
        }
    }

    public function findByPlace($placeId)
    {
        try {
            return $this->repo->findByPlace($placeId);
        } catch (Exception $e) {
            throw new Exception('Error al filtrar servicios por sede: ' . $e->getMessage());
        }
    }
}
