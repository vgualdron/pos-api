<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CompanyService;
use App\Support\CompanyScope;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    protected $service;
    protected array $fields = [
        'name',
        'document_type',
        'document_number',
        'address',
        'phone',
        'email',
        'status',
        'configuration',
    ];

    public function __construct(CompanyService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $companies = CompanyScope::restrict($request->user(), collect($this->service->all()), true);

            return response()->json($companies->values());
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener registros', 'message' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            CompanyScope::assert($request->user(), (int) $id);

            return response()->json($this->service->find($id));
        } catch (Exception $e) {
            return response()->json(['error' => "Error al obtener el registro con ID $id", 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            if (! CompanyScope::canSeeAll($request->user())) {
                throw new Exception('No tiene permiso para crear empresas de otros tenants.');
            }

            $data = $request->only($this->fields);
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
            CompanyScope::assert($request->user(), (int) $id);
            $data = $request->only($this->fields);
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
            CompanyScope::assert($request->user(), (int) $id);
            $r = $this->service->delete($id);
            $m = $r ? 'Registro eliminado con éxito' : 'No se realizó la eliminación del registro';

            return response()->json(['message' => $m], 200);
        } catch (Exception $e) {
            return response()->json(['error' => "Error al eliminar el registro con ID $id", 'message' => $e->getMessage()], 400);
        }
    }

    public function byStatus(Request $request, string $status): JsonResponse
    {
        try {
            $companies = CompanyScope::restrict(
                $request->user(),
                collect($this->service->findByStatuses($status)),
                true
            );

            return response()->json($companies->values());
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener los registros por estado', 'message' => $e->getMessage()], 400);
        }
    }
}
