<?php

namespace App\Http\Requests\Hl7;

use Illuminate\Foundation\Http\FormRequest;

class StoreRdaPacienteRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Asumimos que ya pasó por el middleware de Auth
    }

    public function rules()
    {
        return [
            'tipo_rda' => 'required|string',
            'caja_1_demograficos' => 'required|array',
            'caja_1_demograficos.paciente.tipo_documento' => 'required|string',
            'caja_1_demograficos.paciente.numero_documento' => 'required|string',
            'caja_1_demograficos.paciente.nombres' => 'required|string',
            'caja_1_demograficos.paciente.apellidos' => 'required|string',
            'caja_1_demograficos.paciente.fecha_nacimiento' => 'required|date',
            'caja_1_demograficos.paciente.genero_biologico' => 'required|string',
            
            'caja_antecedentes' => 'required|array',
            
            // Validar que si mandan patologías, vengan bien formadas
            'caja_antecedentes.patologicos' => 'present|array',
            'caja_antecedentes.patologicos.*.codigo_cie10' => 'required_with:caja_antecedentes.patologicos|string',
            
            'caja_antecedentes.farmacologicos' => 'present|array',
            'caja_antecedentes.alergias' => 'present|array',
            'caja_antecedentes.familiares' => 'present|array',
        ];
    }
}