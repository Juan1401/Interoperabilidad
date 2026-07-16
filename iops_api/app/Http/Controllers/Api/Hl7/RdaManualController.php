<?php

namespace App\Http\Controllers\Api\Hl7;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hl7\StoreRdaPacienteRequest;
use App\Models\RdaDocument;
use App\Services\Fhir\RdaPacienteBuilder;
use Illuminate\Http\JsonResponse;

class RdaManualController extends Controller
{
    public function storePaciente(StoreRdaPacienteRequest $request): JsonResponse
    {
        // 1. Obtener al médico que hizo la petición y cargar su organización (IPS)
        /** @var \App\User $user */
        $user = auth()->user();
        $user->load('organization');

        // 2. Obtener los datos limpios que pasaron el validador
        $payload = $request->validated();

        // 3. ZERO TRUST: Inyectamos los datos del médico y la clínica desde el backend
        // Angular no nos manda esto por seguridad, nosotros lo ponemos.

        // Organización (Club Noel)
        $payload['caja_1_demograficos']['organizacion'] = [
            'nit' => $user->organization->nit ?? '',
            'nombre' => $user->organization->razon_social ?? '',
            'codigo_habilitacion' => $user->organization->codigo_habilitacion ?? '',
        ];

        // Profesional (Médico)
        $payload['caja_1_demograficos']['profesional'] = [
            'tipo_documento' => $user->tipo_documento ?? 'CC',
            'numero_documento' => $user->numero_documento ?? '0000000000',
            'nombres' => $user->name,
            'apellidos' => $user->apellidos ?? 'Prueba',
            'especialidad_codigo' => $user->especialidad_codigo ?? '389', // 389 = Medicina General
        ];

        // Hidratar descripciones reales de CIE-10 para evitar rechazos del Ministerio
        if (!empty($payload['caja_antecedentes']['patologicos'])) {
            foreach ($payload['caja_antecedentes']['patologicos'] as &$patologia) {
                if (!empty($patologia['codigo_cie10'])) {
                    $cie10 = \Illuminate\Support\Facades\DB::table('ihce.icd10co')
                        ->where('code', $patologia['codigo_cie10'])->first();
                    if ($cie10) {
                        $patologia['descripcion'] = $cie10->display;
                    }
                }
            }
        }

        if (!empty($payload['caja_antecedentes']['familiares'])) {
            foreach ($payload['caja_antecedentes']['familiares'] as &$familiar) {
                if (!empty($familiar['codigo_cie10'])) {
                    $cie10 = \Illuminate\Support\Facades\DB::table('ihce.icd10co')
                        ->where('code', $familiar['codigo_cie10'])->first();
                    if ($cie10) {
                        $familiar['descripcion'] = $cie10->display;
                    }
                }
            }
        }

        // 4. Guardar en la Base de Datos
        $document = RdaDocument::create([
            'user_id' => $user->id,
            'document_type' => 'RDA_PACIENTE',
            'form_payload' => $payload,
            'status' => 'DRAFT'
        ]);

        // Generar el Bundle FHIR utilizando el Builder
        $builder = new RdaPacienteBuilder();
        $fhirBundle = $builder->build($payload);

        return response()->json([
            'success' => true,
            'fhir_bundle' => $fhirBundle
        ], 201, [], JSON_UNESCAPED_SLASHES);
    }
}