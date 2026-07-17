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
            'caja_1_demograficos.paciente.zona_residencia' => 'required|string',
            'caja_1_demograficos.paciente.codigo_pais' => 'required|string',
            'caja_1_demograficos.paciente.codigo_municipio' => 'required|string',
            'caja_1_demograficos.paciente.etnia' => 'required|string',
            'caja_1_demograficos.paciente.discapacidad' => 'required|string',
            'caja_1_demograficos.paciente.identidad_genero' => 'required|string',
            'caja_1_demograficos.paciente.eapb_codigo' => 'nullable|string',
            
            'caja_antecedentes' => 'required|array',
            
            // Patológicos
            'caja_antecedentes.patologicos' => 'present|array',
            'caja_antecedentes.patologicos.*.codigo_cie10' => 'required|string',
            'caja_antecedentes.patologicos.*.descripcion' => 'nullable|string',
            'caja_antecedentes.patologicos.*.estado' => 'nullable|string',
            
            // Farmacológicos
            'caja_antecedentes.farmacologicos' => 'present|array',
            'caja_antecedentes.farmacologicos.*.medicamento' => 'required|string',
            'caja_antecedentes.farmacologicos.*.medicamento_nombre' => 'nullable|string',
            'caja_antecedentes.farmacologicos.*.dosis_valor' => 'required|numeric',
            'caja_antecedentes.farmacologicos.*.dosis_unidad' => 'required|string',
            'caja_antecedentes.farmacologicos.*.frecuencia_valor' => 'required|numeric',
            'caja_antecedentes.farmacologicos.*.frecuencia_unidad' => 'required|string',
            'caja_antecedentes.farmacologicos.*.via_administracion' => 'required|string',

            // Alergias
            'caja_antecedentes.alergias' => 'present|array',
            'caja_antecedentes.alergias.*.alergeno' => 'required|string',
            'caja_antecedentes.alergias.*.reaccion' => 'nullable|string',
            'caja_antecedentes.alergias.*.severidad' => 'nullable|string',

            // Familiares
            'caja_antecedentes.familiares' => 'present|array',
            'caja_antecedentes.familiares.*.parentesco' => 'required|string',
            'caja_antecedentes.familiares.*.codigo_cie10' => 'required|string',
            'caja_antecedentes.familiares.*.descripcion' => 'nullable|string',
        ];
    }
}