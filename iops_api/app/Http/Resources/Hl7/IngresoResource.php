<?php

namespace App\Http\Resources\Hl7;

use Illuminate\Http\Resources\Json\JsonResource;

class IngresoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'ingreso' => $this->ingreso,
            'tipoIdPaciente' => $this->tipo_id_paciente,
            'pacienteId' => $this->paciente_id,
            'nombre' => $this->nombre,
            'departamento' => $this->departamento,
            'descripcionDepartamento' => $this->descripcion,
            'fechaRegistro' => $this->fecha_registro,
            'fechaRegistroSalida' => $this->fecha_registro_salida,
            'envioId' => $this->envio_id,
            'evolucionId' => $this->evolucion_id,
            'tipoRdaId' => $this->tipo_rda_id,
            'tipoRda' => $this->tipo_rda,
            'estadoEnvioId' => $this->estado_envio_id,
            'estado' => $this->estado,
            'fechaUltimoIntento' => $this->fecha_ultimo_intento,
            'intentosRealizados' => $this->intentos_realizados,
            'codigoRespuestaHttp' => $this->codigo_respuesta_http,
            'claseAtencionFhir' => $this->clase_atencion_fhir,
            'grupoServicioCodigo' => $this->grupo_servicio_codigo,
            'grupoServicioNombre' => $this->grupo_servicio_nombre,
        ];
    }
}
