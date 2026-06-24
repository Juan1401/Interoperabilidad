<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemUsuario;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validar los datos de entrada
        $request->validate([
            'usuario' => 'required|string',
            'password' => 'required|string',
        ]);

        // 2. Buscar al usuario en la base de datos
        $user = SystemUsuario::where('usuario', $request->usuario)->first();

        // 3. Verificar si el usuario existe, la contraseña es correcta Y SI ESTÁ ACTIVO
        if (!$user || md5($request->password) !== $user->passwd || $user->activo !== '1') {
            return response()->json([
                'message' => 'Credenciales inválidas o usuario inactivo'
            ], 401); // 401 Unauthorized
        }

        // 4. Si todo es correcto, devolvemos los datos del usuario
        return response()->json([
            'message' => 'Login exitoso',
            'user' => $user
        ], 200);
    }

    /**
     * Devuelve la información del usuario autenticado a través del token.
     * Este método reemplaza la Closure en el archivo de rutas.
     */
    public function user(Request $request)
    {
        return $request->user();
    }
}
