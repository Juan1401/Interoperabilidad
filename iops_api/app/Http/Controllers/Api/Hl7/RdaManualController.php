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

        // Hidratar descripciones reales de medicamentos para evitar el fallback a PARACETAMOL
        if (!empty($payload['caja_antecedentes']['farmacologicos'])) {
            foreach ($payload['caja_antecedentes']['farmacologicos'] as &$farmaco) {
                if (!empty($farmaco['medicamento'])) {
                    $medCode = $farmaco['medicamento'];
                    // Si llega como array (por ejemplo del p-autoComplete)
                    if (is_array($medCode)) {
                        $medCode = $medCode['value'] ?? '';
                    }

                    // Buscar primero en fhir_cums (CUM)
                    $cum = \Illuminate\Support\Facades\DB::table('ihce.fhir_cums')
                        ->where('code', $medCode)->first();
                    if ($cum) {
                        $farmaco['medicamento_nombre'] = $cum->display;
                    } else {
                        // Buscar en mipres_inn (INN)
                        $inn = \Illuminate\Support\Facades\DB::table('ihce.mipres_inn')
                            ->where('code', $medCode)->first();
                        if ($inn) {
                            $farmaco['medicamento_nombre'] = $inn->display;
                        }
                    }
                }
            }
        }

        // 4. Guardar el payload del formulario en la Base de Datos como borrador inicial
        $document = RdaDocument::create([
            'user_id'       => $user->id,
            'document_type' => 'RDA_PACIENTE',
            'form_payload'  => $payload,
            'status'        => 'DRAFT'
        ]);

        // 5. Generar el Bundle FHIR utilizando el Builder
        $builder     = new RdaPacienteBuilder();
        $fhirBundle  = $builder->build($payload);

        // 6. Persistir el Bundle FHIR generado y marcar el documento como listo
        //    Esto permite auditoría, reintentos de envío y trazabilidad completa.
        $document->update([
            'fhir_bundle_generated' => $fhirBundle,
            'status'                => 'READY'
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'Bundle FHIR generado y almacenado correctamente.',
            'data'       => [
                'document_id' => $document->id,
                'status'      => $document->status,
            ],
            'fhir_bundle' => $fhirBundle
        ], 201, [], JSON_UNESCAPED_SLASHES);
    }
}