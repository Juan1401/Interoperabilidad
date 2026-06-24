<?php

namespace App\Http\Resources\Hl7;

use Illuminate\Http\Resources\Json\JsonResource;

class IhceTipoRdaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'     => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
        ];
    }
}
