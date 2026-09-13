<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\PlaceService;
use App\Support\CompanyScope;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PlaceController extends Controller
{
    protected $service;
    protected array $fields = ['company_id', 'name', 'address', 'phone', 'email', 'print_api_url', 'status'];

    public function __construct(PlaceService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $places = collect($this->service->all());
            $user = $request->user();

            if (! CompanyScope::canSeeAll($user)) {
                $ownPlaceId = PlaceScope::sessionPlaceId($user);
                $places = $places->filter(function ($place) use ($user, $ownPlaceId) {
                    if ($ownPlaceId && (int) $place->id === (int) $ownPlaceId) {
                        return true;
                    }

                    return $user->company_id && (int) $place->company_id === (int) $user->company_id;
                })->values();
            }

            return response()->json($places->values());
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener registros', 'message' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $place = $this->service->find($id);
            $this->assertPlaceAccess($request, $place);

            return response()->json($place);
        } catch (Exception $e) {
            return response()->json(['error' => "Error al obtener el registro con ID $id", 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->only($this->fields);
            CompanyScope::assert($request->user(), (int) ($data['company_id'] ?? 0));
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
            $place = $this->service->find($id);
            $this->assertPlaceAccess($request, $place);
            $data = $request->only($this->fields);
            if (! empty($data['company_id'])) {
                CompanyScope::assert($request->user(), (int) $data['company_id']);
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
            $place = $this->service->find($id);
            $this->assertPlaceAccess($request, $place);
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
            $places = collect($this->service->findByStatuses($status));
            $user = $request->user();

            if (! CompanyScope::canSeeAll($user)) {
                $places = $places->filter(function ($place) use ($user) {
                    return $user->company_id && (int) $place->company_id === (int) $user->company_id;
                })->values();
            }

            return response()->json($places->values());
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener los registros por estado', 'message' => $e->getMessage()], 400);
        }
    }

    public function byCompany(Request $request, $idCompany): JsonResponse
    {
        try {
            CompanyScope::assert($request->user(), (int) $idCompany);

            return response()->json($this->service->findByCompany($idCompany));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener los registros por empresa', 'message' => $e->getMessage()], 400);
        }
    }

    protected function assertPlaceAccess(Request $request, $place): void
    {
        $user = $request->user();
        $ownPlaceId = PlaceScope::sessionPlaceId($user);
        if ($ownPlaceId && (int) $ownPlaceId === (int) $place->id) {
            return;
        }

        CompanyScope::assert($user, (int) $place->company_id);
    }
}
