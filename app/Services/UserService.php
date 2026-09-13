<?php
namespace App\Services;

use App\Repositories\UserRepository;
use Exception;

class UserService
{
    protected $repo;

    public function __construct(UserRepository $repo)
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
            return $this->repo->create($data);
        } catch (Exception $e) {
            throw new Exception("Error al crear el registro: " . $e->getMessage());
        }
    }

    public function update($id, array $data)
    {
        try {
            return $this->repo->update($id, $data);
        } catch (Exception $e) {
            throw new Exception("Error al actualizar el registro con ID {$id}: " . $e->getMessage());
        }
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

    public function findByPlace($idPlace)
    {
        try {
            return $this->repo->findByPlace($idPlace);
        } catch (Exception $e) {
            throw new Exception("Error al filtrar registros por empresa: " . $e->getMessage());
        }
    }

    public function findByCompany($idCompany, $userId = null)
    {
        try {
            return $this->repo->findByCompany($idCompany, $userId);
        } catch (Exception $e) {
            throw new Exception("Error al filtrar registros por empresa: " . $e->getMessage());
        }
    }

    public function findByRoleName($roleName)
    {
        try {
            return $this->repo->findByRoleName($roleName);
        } catch (Exception $e) {
            throw new Exception("Error al filtrar registros por nombre de rol: " . $e->getMessage());
        }
    }
}
