<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PermissionController extends Controller
{
    /**
     * Obtiene los permisos de un usuario para el módulo GestorPro.
     *
     * @param  int  $usuario_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserPermissions($usuario_id)
    {
        $sqlQuery = "
            SELECT
                DISTINCT (SO.opcion_permiso_nombre) AS permiso_nombre
            FROM   public.system_modulos_usuarios_opciones SO,
                   public.system_modulos_usuarios SU
            WHERE  SO.modulo_tipo = 'app'
            AND    SO.modulo = 'GestorPro'
            AND    SO.system_modulo_usuario_id = SU.system_modulo_usuario_id
            AND    SU.usuario_id = :usuario_id_1

            UNION

            SELECT DISTINCT (SO.opcion_permiso_nombre) AS permiso_nombre
            FROM   public.system_modulos_perfiles_opciones SO,
                   public.system_modulos_perfiles SU,
                   public.system_usuarios_perfiles SY
            WHERE  SO.modulo_tipo = 'app'
            AND    SO.modulo = 'GestorPro'
            AND    SO.system_modulo_perfil_id = SU.system_modulo_perfil_id
            AND    SU.perfil_id = SY.perfil_id
            AND    SY.usuario_id = :usuario_id_2
        ";

        try {
            $permissions = DB::select($sqlQuery, [
                'usuario_id_1' => $usuario_id,
                'usuario_id_2' => $usuario_id,
            ]);

            $permissionNames = array_column($permissions, 'permiso_nombre');
            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Permisos obtenidos exitosamente.',
                'data' => $permissionNames
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en PermissionController: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Ocurrió un error interno al procesar la solicitud.'
            ], 500);
        }
    }
}
