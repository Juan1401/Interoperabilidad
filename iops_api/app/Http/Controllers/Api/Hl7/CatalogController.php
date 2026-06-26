<?php

namespace App\Http\Controllers\Api\Hl7;

use App\Http\Controllers\Controller;
use App\Models\ColombianPersonIdentifier;
use App\Models\ColombianGenderGroup;
use App\Models\ColombianResidenceZone;
use App\Models\Municipality;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador de catálogos HL7/FHIR para el formulario manual de RDA.
 *
 * Sirve las listas desplegables y los autocompletados al frontend Angular,
 * formateando siempre la respuesta como [{'label': '...', 'value': '...'}, ...].
 */
class CatalogController extends Controller
{
    /**
     * Retorna los tipos de documento de identificación de personas (ColombianPersonIdentifier).
     * Fuente: tabla ihce.colombian_person_identifier — columnas: code, display.
     *
     * GET /api/hl7/catalogs/tipos-documento
     */
    public function getTiposDocumento(): JsonResponse
    {
        $items = ColombianPersonIdentifier::where('active', true)
            ->orderBy('display')
            ->get(['code', 'display'])
            ->map(fn ($row) => [
                'label' => $row->display,
                'value' => $row->code,
            ]);

        return response()->json($items);
    }

    /**
     * Retorna los grupos de género biológico (ColombianGenderGroup).
     * Fuente: tabla ihce.colombian_gender_group — columnas: code, display.
     *
     * GET /api/hl7/catalogs/generos
     */
    public function getGeneros(): JsonResponse
    {
        $items = ColombianGenderGroup::where('active', true)
            ->orderBy('display')
            ->get(['code', 'display'])
            ->map(fn ($row) => [
                'label' => $row->display,
                'value' => $row->code,
            ]);

        return response()->json($items);
    }

    /**
     * Retorna las zonas de residencia (ColombianResidenceZone).
     * Fuente: tabla ihce.colombian_residence_zone — columnas: code, display.
     *
     * GET /api/hl7/catalogs/zonas
     */
    public function getZonas(): JsonResponse
    {
        $items = ColombianResidenceZone::where('active', true)
            ->orderBy('display')
            ->get(['code', 'display'])
            ->map(fn ($row) => [
                'label' => $row->display,
                'value' => $row->code,
            ]);

        return response()->json($items);
    }

    /**
     * Retorna los municipios de Colombia (Municipality).
     * Fuente: tabla ihce.municipalities — columnas: code, display.
     * Se retornan todos los municipios activos ordenados por nombre.
     *
     * GET /api/hl7/catalogs/municipios
     */
    public function getMunicipios(): JsonResponse
    {
        $items = Municipality::where('active', true)
            ->orderBy('display')
            ->get(['code', 'display'])
            ->map(fn ($row) => [
                'label' => $row->display,
                'value' => $row->code,
            ]);

        return response()->json($items);
    }

    /**
     * Búsqueda de diagnósticos CIE-10 con autocompletado.
     * Fuente: tabla ihce.icd10co — columnas: code, display.
     * Usa ILIKE nativo de PostgreSQL (insensible a mayúsculas/tildes).
     * Limitado a 50 resultados para no saturar el frontend.
     *
     * GET /api/hl7/catalogs/search/diagnosticos?q=hipertension
     */
    public function searchDiagnosticos(Request $request): JsonResponse
    {
        $termino = trim($request->query('q', ''));

        // Mínimo 2 caracteres para evitar consultas masivas sin contexto
        if (strlen($termino) < 2) {
            return response()->json([]);
        }

        $patron = '%' . $termino . '%';

        $items = DB::table('ihce.icd10co')
            ->where('active', true)
            ->where(function ($query) use ($patron) {
                // Busca coincidencia en el código O en la descripción
                $query->where('code', 'ILIKE', $patron)
                      ->orWhere('display', 'ILIKE', $patron);
            })
            ->orderBy('code')
            ->limit(50)
            ->get(['code', 'display'])
            ->map(fn ($row) => [
                // El label muestra "CÓDIGO - Descripción" para que el médico identifique rápido
                'label' => $row->code . ' - ' . $row->display,
                'value' => $row->code,
            ]);

        return response()->json($items);
    }

    /**
     * Búsqueda de medicamentos con autocompletado.
     * Fuente primaria: tabla ihce.fhir_cums (Código Único de Medicamentos).
     * Fuente secundaria: tabla ihce.mipres_inn (Denominación Común Internacional MIPRES).
     * Ambas tablas tienen el mismo esquema: code, display, active.
     * Limitado a 50 resultados.
     *
     * GET /api/hl7/catalogs/search/medicamentos?q=paracetamol
     */
    public function searchMedicamentos(Request $request): JsonResponse
    {
        $termino = trim($request->query('q', ''));

        if (strlen($termino) < 2) {
            return response()->json([]);
        }

        $patron = '%' . $termino . '%';

        // Buscamos primero en fhir_cums (CUM — código oficial MinSalud)
        $items = DB::table('ihce.fhir_cums')
            ->where('active', true)
            ->where(function ($query) use ($patron) {
                $query->where('code', 'ILIKE', $patron)
                      ->orWhere('display', 'ILIKE', $patron);
            })
            ->orderBy('display')
            ->limit(50)
            ->get(['code', 'display'])
            ->map(fn ($row) => [
                'label' => $row->display . ' [CUM: ' . $row->code . ']',
                'value' => $row->code,
            ]);

        // Si no hay resultados en CUM, intentamos con la tabla INN de MIPRES
        if ($items->isEmpty()) {
            $items = DB::table('ihce.mipres_inn')
                ->where('active', true)
                ->where(function ($query) use ($patron) {
                    $query->where('code', 'ILIKE', $patron)
                          ->orWhere('display', 'ILIKE', $patron);
                })
                ->orderBy('display')
                ->limit(50)
                ->get(['code', 'display'])
                ->map(fn ($row) => [
                    'label' => $row->display . ' [INN]',
                    'value' => $row->code,
                ]);
        }

        return response()->json($items);
    }
}

