<?php

namespace App\Http\Resources\Hl7;

use Illuminate\Http\Resources\Json\JsonResource;

class IhceLogEnvioResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Nota: json_enviado y mensaje_respuesta son columnas jsonb en PostgreSQL.
     * Laravel/PDO las retorna ya como string JSON, por lo que se decodifican
     * a array para que el frontend las reciba como objeto nativo.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        $data = [];

        // json_enviado (jsonb): decodificar de string a array/objeto
        if (property_exists($this->resource, 'json_enviado')) {
            $raw = $this->json_enviado;
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            $data['jsonEnviado'] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $raw;
        }

        // mensaje_respuesta (jsonb): decodificar de string a array/objeto
        if (property_exists($this->resource, 'mensaje_respuesta')) {
            $raw = $this->mensaje_respuesta;
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            $data['mensajeRespuesta'] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $raw;
        }

        // Metadatos comunes del log y del envío
        $data['logId']              = $this->log_id                ?? null;
        $data['envioId']            = $this->envio_id              ?? null;
        $data['ingresoId']          = $this->ingreso_id            ?? null;
        $data['fechaEvento']        = $this->fecha_evento          ?? null;
        $data['codigoRespuesta']    = $this->codigo_respuesta_http ?? null;
        $data['estadoEnvioId']      = $this->estado_envio_id       ?? null;
        $data['intentosRealizados'] = $this->intentos_realizados   ?? null;

        return $data;
    }
}
