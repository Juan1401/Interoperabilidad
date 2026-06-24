<?php

namespace App\Services\Ihce;

use App\Models\Ihce\AuditoriaAccesoLink;
use Exception;
use Illuminate\Support\Facades\Log;

class AuditoriaAccesoLinkService
{
    /**
     * Stores a new audit access link record if it doesn't already exist.
     *
     * @param array $data
     * @return array
     */
    public function storeAuditRecord(array $data): array
    {
        try {
            // Check if the record already exists using the provided UUID
            if (AuditoriaAccesoLink::where('uuid', $data['uuid'])->exists()) {
                return [
                    'status' => false,
                    'message' => 'El registro ya existe'
                ];
            }

            // Create the new record
            AuditoriaAccesoLink::create([
                'uuid' => $data['uuid'],
                'usuario_id' => $data['usuario_id'],
                'ip' => $data['ip'],
                'estado' => $data['estado'] ?? 'Usado',
            ]);

            return [
                'status' => true,
                'message' => 'Datos registrados exitosamente'
            ];

        } catch (Exception $e) {
            Log::error('Error saving auditoria_accesos_links record. Error: ' . $e->getMessage());
            
            return [
                'status' => false,
                'message' => 'Error interno al registrar los datos',
                'error' => $e->getMessage()
            ];
        }
    }
}
