<?php

namespace App\Http\Resources\Hl7;

use Illuminate\Http\Resources\Json\JsonResource;

class TipoIdPacienteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        return [
            'tipoId'      => $this->tipo_id_paciente,
            'abreviatura' => $this->tipo_id_paciente,
            'descripcion' => $this->descripcion,
            'indiceOrden' => $this->indice_de_orden,
        ];
    }
}
