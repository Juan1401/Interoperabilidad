<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'usuario' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('usuario', $request->usuario)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales invalidas'
            ], 401);
        }

        $tokenResult = $user->createToken('hl7_auth_token');
        
        return response()->json([
            'access_token' => $tokenResult->accessToken,
            'token_type'   => 'Bearer',
            'message'      => 'Login exitoso',
            'user'         => [
                'id'                  => $user->id,
                'name'                => $user->name,
                'apellidos'           => $user->apellidos,
                'email'               => $user->email,
                'usuario'             => $user->usuario,
                'organization_id'     => $user->organization_id,
                'tipo_documento'      => $user->tipo_documento,
                'numero_documento'    => $user->numero_documento,
                'especialidad_codigo' => $user->especialidad_codigo,
            ]
        ], 200);

    }

    public function user(Request $request)
    {
        return $request->user();
    }

    /**
     * Retorna la dirección IP real del cliente que realiza la solicitud.
     * Respeta cabeceras de proxy inverso (Nginx / Load Balancer) si están presentes.
     * Este endpoint es público (middleware 'client') para consultarse antes del login.
     */
    public function getClientIp(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'ip' => $request->ip(),
        ]);
    }
}
