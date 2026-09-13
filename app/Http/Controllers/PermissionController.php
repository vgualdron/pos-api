<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    protected $service;
    protected array $fields = ['name', 'icon', 'guard_name' , 'subgroup', 'label', 'group_id', 'order', 'route'];

    public function __construct(PermissionService $service)
    {
        $this->service = $service;
    }

    public function index(): JsonResponse
    {
        try {
            return response()->json($this->service->all());
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener registros', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            return response()->json($this->service->find($id));
        } catch (Exception $e) {
            return response()->json(['error' => "Error al obtener el registro con ID $id", 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
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
            $data = $request->only($this->fields);
            $r = $this->service->update($id, $data);
            $m = $r ? 'Registro modificado con éxito' : 'No se realizó la modificación del registro';
            return response()->json(['message' => $m], 200);
        } catch (Exception $e) {
            return response()->json(['error' => "Error al actualizar el registro con ID $id", 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
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
}
