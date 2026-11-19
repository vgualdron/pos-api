<?php

namespace App\Http\Controllers;

use App\Services\CustomerService;
use App\Support\DianCustomerCatalog;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(protected CustomerService $service)
    {
    }

    public function catalog(): JsonResponse
    {
        return response()->json([
            'person_types' => [
                ['value' => DianCustomerCatalog::PERSON_NATURAL, 'label' => 'Persona natural'],
                ['value' => DianCustomerCatalog::PERSON_JURIDICA, 'label' => 'Persona jurídica'],
            ],
            'document_types' => collect(DianCustomerCatalog::DOCUMENT_TYPES)
                ->map(fn ($label, $value) => ['value' => (string) $value, 'label' => "{$value} — {$label}"])
                ->values(),
            'tax_regimes' => collect(DianCustomerCatalog::TAX_REGIMES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'fiscal_responsibilities' => collect(DianCustomerCatalog::FISCAL_RESPONSIBILITIES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => "{$value} — {$label}"])
                ->values(),
        ]);
    }

    public function byPlace(Request $request, $place): JsonResponse
    {
        try {
            PlaceScope::assert(
                $request->user(),
                (int) $place,
                'customers.allPlaces',
                'sales.allPlaces'
            );

            $onlyActive = filter_var($request->query('active', false), FILTER_VALIDATE_BOOLEAN);
            $search = $request->query('search');

            return response()->json($this->service->byPlace((int) $place, $onlyActive, $search));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar clientes', 'message' => $e->getMessage()], 400);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $record = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $record->place_id, 'customers.allPlaces', 'sales.allPlaces');

            return response()->json($record);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener cliente', 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate($this->rules(true));
            PlaceScope::assert($request->user(), (int) $data['place_id'], 'customers.allPlaces', 'sales.allPlaces');
            $record = $this->service->create($data);

            return response()->json(['message' => 'Cliente creado', 'data' => $record], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al crear cliente', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $existing->place_id, 'customers.allPlaces', 'sales.allPlaces');
            $data = $request->validate($this->rules(false));
            $record = $this->service->update((int) $id, $data);

            return response()->json(['message' => 'Cliente actualizado', 'data' => $record]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al actualizar cliente', 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $existing->place_id, 'customers.allPlaces', 'sales.allPlaces');
            $this->service->delete((int) $id);

            return response()->json(['message' => 'Cliente eliminado']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al eliminar cliente', 'message' => $e->getMessage()], 400);
        }
    }

    protected function rules(bool $creating): array
    {
        $base = [
            'person_type' => ($creating ? 'required' : 'sometimes') . '|string|in:natural,juridica',
            'document_type' => ($creating ? 'required' : 'sometimes') . '|string|max:10',
            'document_number' => ($creating ? 'required' : 'sometimes') . '|string|max:40',
            'verification_digit' => 'nullable|string|max:2',
            'legal_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:120',
            'first_last_name' => 'nullable|string|max:120',
            'second_last_name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:120',
            'phone' => 'nullable|string|max:40',
            'mobile' => 'nullable|string|max:40',
            'address_line' => 'nullable|string|max:255',
            'city_code' => 'nullable|string|max:10',
            'city_name' => 'nullable|string|max:120',
            'department_code' => 'nullable|string|max:10',
            'department_name' => 'nullable|string|max:120',
            'country_code' => 'nullable|string|max:5',
            'tax_regime' => 'nullable|string|max:40',
            'fiscal_responsibilities' => 'nullable|array',
            'fiscal_responsibilities.*' => 'string|max:20',
            'merchant_registration' => 'nullable|string|max:80',
            'status' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ];

        if ($creating) {
            $base['place_id'] = 'required|integer|exists:places,id';
        }

        return $base;
    }
}
