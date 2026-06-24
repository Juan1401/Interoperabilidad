<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Ihce;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRdaAuditLogRequest;
use App\Models\RdaAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RdaAuditController extends Controller
{
    /**
     * Registra de forma silenciosa la visualización de un documento RDA.
     */
    public function storeRdaView(StoreRdaAuditLogRequest $request): JsonResponse
    {
        try {
            // El token OAuth de la app usa flujo client_credentials, por lo que
            // $request->user() siempre es null. El user_id real del médico viene
            // explícitamente en el body del request, enviado por el frontend.
            $userId = (int) $request->input('user_id', 0);

            $auditLog = RdaAuditLog::create([
                'user_id'                => $userId,
                'patient_document_type'  => $request->patient_document_type,
                'patient_document_number' => $request->patient_document_number,
                'tipo_rda_id'            => $request->tipo_rda_id,
                'rda_id'                 => $request->rda_id,
            ]);

            // Devuelve la respuesta exitosa junto con los datos guardados
            return response()->json([
                'success' => true,
                'data' => $auditLog
            ], 201); // 201 Created

        } catch (\Throwable $e) {
            // Manejo de error explícito devolviéndolo al cliente
            Log::error('Fallo en auditoría RDA: ' . $e->getMessage(), [
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la auditoría de visualización',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
