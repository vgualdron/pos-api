<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Municipality;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    public function departments(): JsonResponse
    {
        try {
            $rows = Department::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name']);

            return response()->json($rows);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar departamentos', 'message' => $e->getMessage()], 400);
        }
    }

    public function municipalities(Request $request): JsonResponse
    {
        try {
            $query = Municipality::query()
                ->with(['department:id,code,name'])
                ->orderBy('name');

            if ($request->filled('department_id')) {
                $query->where('department_id', (int) $request->query('department_id'));
            } elseif ($request->filled('department_code')) {
                $code = str_pad((string) $request->query('department_code'), 2, '0', STR_PAD_LEFT);
                $query->whereHas('department', fn ($q) => $q->where('code', $code));
            }

            if ($request->filled('search')) {
                $term = '%' . trim((string) $request->query('search')) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)->orWhere('code', 'like', $term);
                });
            }

            return response()->json($query->limit(1500)->get(['id', 'department_id', 'code', 'name']));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar municipios', 'message' => $e->getMessage()], 400);
        }
    }
}
