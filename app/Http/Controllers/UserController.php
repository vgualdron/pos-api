<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Services\UserService;
use App\Support\CompanyScope;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    protected $service;
    protected array $fields = [
        'company_id',
        'place_id',
        'station_id',
        'name',
        'email',
        'status',
        'password',
        'roles',
    ];

    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (CompanyScope::canSeeAll($user, true)) {
                return response()->json($this->service->all());
            }

            if (! $user->company_id) {
                return response()->json([]);
            }

            return response()->json($this->service->findByCompany($user->company_id));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener registros', 'message' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $record = $this->service->find($id);
            CompanyScope::assert($request->user(), (int) $record->company_id, true);

            return response()->json($record);
        } catch (Exception $e) {
            return response()->json(['error' => "Error al obtener el registro con ID $id", 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->only($this->fields);
            CompanyScope::assert($request->user(), (int) ($data['company_id'] ?? 0), true);
            $r = $this->service->create($data);
            $m = $r ? 'Registro creado con éxito' : 'No se realizó la creación del registro';
            return response()->json(['message' => $m], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al crear el registros', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find($id);
            CompanyScope::assert($request->user(), (int) $existing->company_id, true);
            $data = $request->only($this->fields);
            if (! empty($data['company_id'])) {
                CompanyScope::assert($request->user(), (int) $data['company_id'], true);
            }
            $r = $this->service->update($id, $data);
            $m = $r ? 'Registro modificado con éxito' : 'No se realizó la modificación del registro';
            return response()->json(['message' => $m], 200);
        } catch (Exception $e) {
            return response()->json(['error' => "Error al actualizar el registro con ID $id", 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find($id);
            CompanyScope::assert($request->user(), (int) $existing->company_id, true);
            $r = $this->service->delete($id);
            $m = $r ? 'Registro eliminado con éxito' : 'No se realizó la eliminación del registro';
            return response()->json(['message' => $m], 200);
        } catch (Exception $e) {
            return response()->json(['error' => "Error al eliminar el registro con ID $id", 'message' => $e->getMessage()], 400);
        }
    }

    public function byStatus(string $status): JsonResponse
    {
        try {
            return response()->json($this->service->findByStatuses($status));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener los registros por estado', 'message' => $e->getMessage()], 400);
        }
    }

    public function byPlace(Request $request, $idPlace): JsonResponse
    {
        try {
            $place = Place::query()->find($idPlace);
            if (! $place) {
                throw new Exception('La sede no está disponible.');
            }
            CompanyScope::assert($request->user(), (int) $place->company_id, true);

            return response()->json($this->service->findByPlace($idPlace));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener los registros por empresa', 'message' => $e->getMessage()], 400);
        }
    }

    public function byCompany(Request $request, $idCompany): JsonResponse
    {
        try {
            CompanyScope::assert($request->user(), (int) $idCompany, true);
            $userId = $request->user()->id;

            return response()->json($this->service->findByCompany($idCompany, $userId));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener los registros por empresa', 'message' => $e->getMessage()], 400);
        }
    }

    public function byRoleName($roleName): JsonResponse
    {
        try {
            $userId = auth()->id();
            return response()->json($this->service->findByRoleName($roleName));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener los registros por nombre de rol', 'message' => $e->getMessage()], 400);
        }
    }
}
