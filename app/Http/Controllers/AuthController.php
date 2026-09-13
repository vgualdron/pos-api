<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AuthService;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        try {
            return response()->json(
                $this->authService->login($request->email, $request->password)
            );
        } catch (\Exception $e) {
             return response()->json(['error' => $e->getMessage(), 'message' => 'Error al iniciar sesión: ' . $e->getMessage()], 404);
        }
    }

    public function context(Request $request)
    {
        try {
            return response()->json($this->authService->context($request->user()));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'message' => 'Error al obtener contexto de estación'], 400);
        }
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());
        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        try {
            $this->authService->changePassword(
                $request->user(),
                $request->current_password,
                $request->password
            );

            return response()->json(['message' => 'Contraseña actualizada correctamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
