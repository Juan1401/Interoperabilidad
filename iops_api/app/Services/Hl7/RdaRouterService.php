<?php

namespace App\Services\Hl7;

use Illuminate\Support\Facades\DB;
use App\Services\Hl7\RdaUrgenciasService;
use App\Services\Hl7\RdaHospitalizacionService;
use App\Services\Hl7\RdaConsultaService;

class RdaRouterService
{
    /**
     * Inyección de dependencias usando Constructor Property Promotion (PHP 8+)
     */
    public function __construct(
        protected RdaUrgenciasService $rdaUrgenciasService,
        protected RdaHospitalizacionService $rdaHospitalizacionService,
        protected RdaConsultaService $rdaConsultaService
    ) {}

    /**
     * Determina la clase de atención basada en reglas de negocio (Optimizado con DB::exists)
     *
     * @param int $ingresoId
     * @return string (IMP, EMER o AMB)
     */
    public function determinarClaseAtencion(int $ingresoId): string
    {
        // 1. Verificamos si existe hospitalización (IMP)
        $isHospitalizacion = DB::table('public.movimientos_habitacion as mh')
            ->join('public.estaciones_enfermeria as ee', 'mh.estacion_id', '=', 'ee.estacion_id')
            ->where('mh.ingreso', $ingresoId)
            ->where('ee.sw_hospitalizacion_rips', '1')
            ->exists();

        if ($isHospitalizacion) {
            return 'IMP';
        }

        // 2. Si no es hospitalización, verificamos si es urgencias (EMER)
        $isUrgencias = DB::table('public.movimientos_habitacion as mh')
            ->join('public.estaciones_enfermeria as ee', 'mh.estacion_id', '=', 'ee.estacion_id')
            ->where('mh.ingreso', $ingresoId)
            ->where('ee.sw_hospitalizacion_rips', '0')
            ->exists();

        if ($isUrgencias) {
            return 'EMER';
        }

        // 3. Por defecto es ambulatorio
        return 'AMB';
    }

    /**
     * Obtiene el nombre del método interno en el controlador que procesa este RDA
     * 
     * @param string $claseAtencion
     * @return string
     */
    public function obtenerRutaDestino(string $claseAtencion): string
    {
        return match ($claseAtencion) {
            'EMER' => 'getRdaUrgencias',
            'IMP'  => 'getRdaHospitalizacion',
            'AMB'  => 'getRdaConsulta',
            default => throw new \Exception("Clase de atención FHIR no soportada: {$claseAtencion}"),
        };
    }
}
