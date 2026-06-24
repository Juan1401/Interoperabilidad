<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Hl7\IhceLogEnvioResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemVariablesController extends Controller
{
    /**
     * Devuelve los resultados de la consulta filtrando por modulo, modulo_tipo y variable.
     */
    public function getVariable(Request $request)
    {
        try {
            // Validar que se envíen los parámetros necesarios
            $request->validate([
                'modulo' => 'required|string',
                'modulo_tipo' => 'required|string',
                'variable' => 'required|string',
            ]);

            $modulo = $request->input('modulo');
            $modulo_tipo = $request->input('modulo_tipo');
            $variable = $request->input('variable');

            $query = "
                SELECT modulo, modulo_tipo, variable, valor, descripcion
                FROM public.system_modulos_variables
                WHERE modulo = ? AND modulo_tipo = ? AND variable = ?
            ";

            $results = DB::select($query, [$modulo, $modulo_tipo, $variable]);

            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan parámetros requeridos o son inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar la consulta: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Devuelve el listado optimizado de Tipos de Identificación de Paciente.
     */
    public function getTiposIdPacientes(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $tipos = \App\Models\TipoIdPaciente::select('tipo_id_paciente', 'descripcion', 'indice_de_orden')
                ->orderBy('indice_de_orden', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => \App\Http\Resources\Hl7\TipoIdPacienteResource::collection($tipos)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar la consulta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Devuelve el catálogo optimizado de estados de envío IHCE.
     */
    public function getIhceCatEstadosEnvio(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // Utilizando Query Builder DB::table para optimización máxima tal como fue requerido.
            $estados = \Illuminate\Support\Facades\DB::table('ihce.ihce_cat_estados_envio as icee')
                ->select('icee.id', 'icee.nombre')
                ->orderBy('icee.nombre', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => \App\Http\Resources\Hl7\IhceEstadoEnvioResource::collection($estados)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar la consulta de estados de envío: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Devuelve el catálogo de tipos de RDA (Registro Digital de Atenciones).
     */
    public function getIhceCatTiposRda(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // Seleccionar únicamente las columnas especificadas para ahorrar memoria
            $tiposRda = \Illuminate\Support\Facades\DB::table('ihce.ihce_cat_tipos_rda')
                ->select('id', 'codigo', 'nombre')
                ->orderBy('nombre', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => \App\Http\Resources\Hl7\IhceTipoRdaResource::collection($tiposRda)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar la consulta de tipos de RDA: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna el último JSON enviado al Ministerio para un ingreso dado.
     *
     * Body esperado:
     * { "ingreso_id": 2302121 }
     */
    public function getUltimoJsonEnviado(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ingreso_id' => 'required|numeric',
                'tipo_rda_id' => 'nullable|numeric',
            ]);

            $query = DB::table('ihce.ihce_control_envios as ice')
                ->join('ihce.ihce_control_envios_logs as icel', 'icel.envio_id', '=', 'ice.envio_id')
                ->where('ice.ingreso_id', $request->input('ingreso_id'));

            if ($request->filled('tipo_rda_id')) {
                $query->where('ice.tipo_rda_id', $request->input('tipo_rda_id'));
            }

            $log = $query->orderBy('icel.log_id', 'desc')
                ->select(
                    'icel.log_id',
                    'icel.envio_id',
                    'ice.ingreso_id',
                    'icel.json_enviado',
                    'icel.fecha_evento',
                    'ice.codigo_respuesta_http',
                    'ice.estado_envio_id',
                    'ice.intentos_realizados'
                )
                ->first();

            if (!$log) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró ningún log de envío para el ingreso indicado.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => new IhceLogEnvioResource($log),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros inválidos',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el JSON enviado: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna la última respuesta recibida del Ministerio para un ingreso dado.
     *
     * Body esperado:
     * { "ingreso_id": 2302121 }
     */
    public function getUltimaRespuestaEnvio(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ingreso_id' => 'required|numeric',
                'tipo_rda_id' => 'nullable|numeric',
            ]);

            $query = DB::table('ihce.ihce_control_envios as ice')
                ->join('ihce.ihce_control_envios_logs as icel', 'icel.envio_id', '=', 'ice.envio_id')
                ->where('ice.ingreso_id', $request->input('ingreso_id'));

            if ($request->filled('tipo_rda_id')) {
                $query->where('ice.tipo_rda_id', $request->input('tipo_rda_id'));
            }

            $log = $query->orderBy('icel.log_id', 'desc')
                ->select(
                    'icel.log_id',
                    'icel.envio_id',
                    'ice.ingreso_id',
                    'icel.mensaje_respuesta',
                    'icel.fecha_evento',
                    'ice.codigo_respuesta_http',
                    'ice.estado_envio_id',
                    'ice.intentos_realizados'
                )
                ->first();

            if (!$log) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró ninguna respuesta de envío para el ingreso indicado.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => new IhceLogEnvioResource($log),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros inválidos',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar la respuesta de envío: ' . $e->getMessage(),
            ], 500);
        }
    }
}
