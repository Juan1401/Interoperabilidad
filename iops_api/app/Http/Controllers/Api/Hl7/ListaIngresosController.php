<?php

namespace App\Http\Controllers\Api\Hl7;

use App\Http\Controllers\Controller;
use App\Http\Resources\Hl7\IngresoResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListaIngresosController extends Controller
{
    /**
     * Devuelve el listado optimizado de ingresos filtrados.
     * 
     * Payload esperado del frontend (JSON):
     * {
     *   "fechaInicio": "YYYY-MM-DD",  // REQUERIDO
     *   "fechaFin": "YYYY-MM-DD",     // REQUERIDO
     *   "tipoDocumento": "CC",        // Opcional, pero requerido si viene 'documento'.
     *   "documento": "12345678",      // Opcional, pero requerido si viene 'tipoDocumento'.
     *   "estado": 2,                  // Opcional (ID del estado), si es 1 asume "Sin enviar" (null en DB).
     *   "noIngreso": 2135353,         // Opcional.
     *   "atencionMedica": 1           // Opcional (ID del tipo de rda).
     *   "claseAtencionFhir": "EMER"   // Opcional. Valores: 'EMER' (Urgencias), 'IMP' (Internación), 'AMB' (Consulta Externa).
     * }
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\JsonResponse
     * 
     */
    public function income(Request $request)
    {
        // 1. Validar parámetros. Todos opcionales pero codependientes
        try {
            $request->validate([
                'fechaInicio' => 'required|date_format:Y-m-d',
                'fechaFin'    => 'required|date_format:Y-m-d|after_or_equal:fechaInicio',
                'tipoDocumento'    => 'required_with:documento|nullable|string',
                'documento'        => 'required_with:tipoDocumento|nullable|string',
                'estado'           => 'nullable|integer',
                'noIngreso'        => 'nullable|numeric',
                'atencionMedica'   => 'nullable|integer',
                'claseAtencionFhir' => 'nullable|string|in:EMER,IMP,AMB',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros inválidos',
                'errors'  => $e->errors()
            ], 422);
        }

        // Subconsultas para la lógica de habitaciones (prioridades de atención médica)
        $hospExistsQuery = DB::table('public.movimientos_habitacion as mh')
            ->join('public.estaciones_enfermeria as ee', 'mh.estacion_id', '=', 'ee.estacion_id')
            ->whereColumn('mh.ingreso', 'i.ingreso')
            ->where('ee.sw_hospitalizacion_rips', '1')
            ->selectRaw('1');

        $urgExistsQuery = DB::table('public.movimientos_habitacion as mh')
            ->join('public.estaciones_enfermeria as ee', 'mh.estacion_id', '=', 'ee.estacion_id')
            ->whereColumn('mh.ingreso', 'i.ingreso')
            ->where('ee.sw_hospitalizacion_rips', '0')
            ->selectRaw('1');

        // Construcción de la consulta LATERAL dinámica
        $lateralQuery = DB::query()
            ->selectRaw("
                CASE 
                    WHEN EXISTS ({$hospExistsQuery->toSql()}) THEN '1'
                    WHEN EXISTS ({$urgExistsQuery->toSql()}) THEN '0'
                    ELSE 'NA'
                END AS tipo_atencion
            ", array_merge($hospExistsQuery->getBindings(), $urgExistsQuery->getBindings()));

        // 2. Construir la consulta base optimizada con DB::table nativo
        $query = DB::table('public.ingresos as i')
            ->join('public.pacientes as p', function ($join) {
                // Join en llaves compuestas requeridas para tabla de pacientes
                $join->on('i.tipo_id_paciente', '=', 'p.tipo_id_paciente')
                    ->on('i.paciente_id', '=', 'p.paciente_id');
            })
            ->join('public.departamentos as d', 'i.departamento', '=', 'd.departamento')
            ->leftJoin('ihce.ihce_control_envios as ice', 'i.ingreso', '=', 'ice.ingreso_id')
            ->leftJoin('ihce.ihce_cat_estados_envio as icee', 'ice.estado_envio_id', '=', 'icee.id')
            ->leftJoin('ihce.ihce_cat_tipos_rda as ictr', 'ictr.id', '=', 'ice.tipo_rda_id')
            ->join('public.ingresos_salidas as isd', 'i.ingreso', '=', 'isd.ingreso') // Inner join para salidas
            ->leftJoinLateral($lateralQuery, 'fhir')
            ->whereIn('i.estado', ['0', '2'])
            ->whereNotNull('i.fecha_cierre')
            ->where('i.fecha_registro', '>=', env('INGRESOS_FECHA_MINIMA', '2025-01-01'));

        // 3. Aplicar Filtros Dinámicos

        // Rango de fechas (Opcional en pareja)
        if ($request->filled('fechaInicio') && $request->filled('fechaFin')) {
            $fechaInicio = $request->input('fechaInicio') . ' 00:00:00';
            $fechaFin = $request->input('fechaFin') . ' 23:59:59';
            $query->whereBetween('isd.fecha_registro', [$fechaInicio, $fechaFin]);
        }

        // Tipo de documento y Número de documento (Opcional, codependiente)
        if ($request->filled('tipoDocumento') && $request->filled('documento')) {
            $query->where('i.tipo_id_paciente', $request->input('tipoDocumento'))
                ->where('i.paciente_id', $request->input('documento'));
        }

        // Estado (Opcional)
        // El id 1 en el frontend (según lógica previa) suele mapearse a "Sin Enviar", que es estado_envio_id = nulo
        if ($request->filled('estado')) {
            $estadoEnvioId = $request->input('estado');
            if ($estadoEnvioId == 1) {
                $query->whereNull('ice.estado_envio_id');
            } else {
                $query->where('ice.estado_envio_id', $estadoEnvioId);
            }
        }

        // Número de Ingreso (Opcional)
        if ($request->filled('noIngreso')) {
            $query->where('i.ingreso', $request->input('noIngreso'));
        }

        // Atención Médica - Tipo RDA (Opcional)
        if ($request->filled('atencionMedica')) {
            $query->where('ice.tipo_rda_id', $request->input('atencionMedica'));
        }

        // Clase de Atención FHIR (Opcional)
        // Traduce el código FHIR al valor interno de fhir.tipo_atencion generado por la subconsulta lateral:
        //   'EMER' → tipo_atencion = '0'  (Urgencias:          sw_hospitalizacion_rips = '0')
        //   'IMP'  → tipo_atencion = '1'  (Internación:        sw_hospitalizacion_rips = '1')
        //   'AMB'  → tipo_atencion = 'NA' (Consulta Externa:   sin movimientos de habitación)
        if ($request->filled('claseAtencionFhir')) {
            $mapaFhir = [
                'EMER' => '0',
                'IMP'  => '1',
                'AMB'  => 'NA',
            ];
            $tipoAtencionInterno = $mapaFhir[$request->input('claseAtencionFhir')];
            $query->where('fhir.tipo_atencion', $tipoAtencionInterno);
        }

        // 4. Configurar el ordenamiento respectivo de la consulta SQL
        $query->orderBy('i.ingreso', 'asc')
            ->orderBy('i.fecha_registro', 'desc');

        // 5. Especificar selección de columnas
        $query->select(
            'i.ingreso',
            'i.tipo_id_paciente',
            'i.paciente_id',
            // Concatenación segura de los nombres eliminando espacios nulos con TRIM y concat_ws
            DB::raw("TRIM(REGEXP_REPLACE(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido), '\s+', ' ', 'g')) as nombre"),
            'd.departamento',
            'd.descripcion',
            'i.fecha_registro',
            'isd.fecha_registro as fecha_registro_salida',
            'ice.envio_id',
            'ice.evolucion_id',
            'ice.tipo_rda_id',
            'ictr.nombre as tipo_rda',
            'ice.estado_envio_id',
            'icee.nombre as estado',
            'ice.fecha_ultimo_intento',
            'ice.intentos_realizados',
            'ice.codigo_respuesta_http'
        )
        ->selectRaw("CASE fhir.tipo_atencion WHEN '1' THEN 'IMP' WHEN '0' THEN 'EMER' ELSE 'AMB' END AS clase_atencion_fhir")
        ->selectRaw("CASE fhir.tipo_atencion WHEN '1' THEN '03' WHEN '0' THEN '02' ELSE '01' END AS grupo_servicio_codigo")
        ->selectRaw("CASE fhir.tipo_atencion WHEN '1' THEN 'Internación' WHEN '0' THEN 'Urgencias' ELSE 'Consulta Externa' END AS grupo_servicio_nombre");

        // 6. Paginación y Respondedor
        // Paginación por Eloquent/QueryBuilder de 100 registros por página
        $ingresos = $query->paginate(100);

        return IngresoResource::collection($ingresos);
    }
}
