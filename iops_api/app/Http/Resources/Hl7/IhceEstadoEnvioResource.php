<?php

namespace App\Http\Resources\Hl7;

use Illuminate\Http\Resources\Json\JsonResource;

class IhceEstadoEnvioResource extends JsonResource
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
            'value' => $this->id,
            'label' => $this->nombre,
        ];
    }
}
