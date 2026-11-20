<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Tax::query()->orderBy('sort_order')->orderBy('name');

            if ($request->boolean('active_only', true)) {
                $query->active();
            }

            return response()->json([
                'data' => $query->get(),
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar impuestos', 'message' => $e->getMessage()], 400);
        }
    }
}
