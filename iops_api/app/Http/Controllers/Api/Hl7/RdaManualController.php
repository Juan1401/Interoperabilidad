<?php

namespace App\Http\Controllers\Api\Hl7;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hl7\StoreRdaPacienteRequest;
use App\Models\RdaDocument;
use Illuminate\Http\JsonResponse;

class RdaManualController extends Controller
{
    public function storePaciente(StoreRdaPacienteRequest $request): JsonResponse
    {
        // 1. Obtener al médico que hizo la petición
        $user = auth()->user();

        // 2. Obtener los datos limpios que pasaron el validador
        $payload = $request->validated();

        // 3. ZERO TRUST: Inyectamos los datos del médico y la clínica desde el backend
        // Angular no nos manda esto por seguridad, nosotros lo ponemos.
        $payload['caja_1_demograficos']['profesional'] = [
            'nombres' => $user->name,
            'email' => $user->email,
            // 'especialidad_rethus' => $user->profesional->especialidad ... etc (Ajustarás esto luego)
        ];

        // 4. Guardar en la Base de Datos
        $document = RdaDocument::create([
            'user_id' => $user->id,
            'document_type' => 'RDA_PACIENTE',
            'form_payload' => $payload,
            'status' => 'DRAFT'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'RDA de Paciente guardado en borrador exitosamente.',
            'data' => [
                'document_id' => $document->id
            ]
        ], 201);
    }
}