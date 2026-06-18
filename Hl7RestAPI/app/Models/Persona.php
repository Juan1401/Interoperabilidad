<?php

namespace App\Models;

/**
 * Clase DTO para estandarizar los datos de una Persona/Paciente
 * para su uso en los servicios RDA y generación de JSON FHIR.
 * No es un Modelo de Eloquent.
 */
class Persona
{
    public $documento;
    public $tipo_documento;
    public $primer_nombre;
    public $segundo_nombre;
    public $primer_apellido;
    public $segundo_apellido;
    public $fecha_nacimiento;
    public $sexo;
    public $direccion;
    public $telefono;
    public $municipio;
    public $departamento;
    public $pais;
    public $zona;
    // Campos FHIR Minsalud (agregados en migración 2026_03_17)
    public $codigo_etnia;
    public $codigo_discapacidad;
    public $municipio_residencia_divipola_id;
    public $zona_residencia_fhir; // Campo FHIR: '01' Urbana, '02' Rural (distinto al campo HIS 'zona')

    /**
     * Constructor para inicializar la clase desde un modelo Pacientes
     */
    public function __construct(Pacientes $paciente)
    {
        $this->documento = $paciente->paciente_id;
        $this->tipo_documento = $paciente->tipo_id_paciente;
        $this->primer_nombre = $paciente->primer_nombre;
        $this->segundo_nombre = $paciente->segundo_nombre;
        $this->primer_apellido = $paciente->primer_apellido;
        $this->segundo_apellido = $paciente->segundo_apellido;
        $this->fecha_nacimiento = $paciente->fecha_nacimiento;
        $this->sexo = $paciente->sexo_id;

        // Mapeo de datos demográficos y de ubicación
        $this->direccion = $paciente->residencia_direccion;
        $this->telefono = $paciente->residencia_telefono;
        $this->municipio = $paciente->tipo_mpio_id;
        $this->departamento = $paciente->tipo_dpto_id;
        $this->pais = $paciente->tipo_pais_id;
        $this->zona = $paciente->zona_residencia;

        // Campos FHIR Minsalud – se leen de BD si existen, si no null (el Builder usará defaults hardcodeados)
        $this->codigo_etnia = $paciente->codigo_etnia ?? null;
        $this->codigo_discapacidad = $paciente->codigo_discapacidad ?? null;
        $this->municipio_residencia_divipola_id = $paciente->municipio_residencia_divipola_id ?? null;
        $this->zona_residencia_fhir = $paciente->zona_residencia ?? null;
    }

    /**
     * Obtiene el nombre completo concatenado
     */
    public function getNombreCompleto(): string
    {
        return trim(
            ($this->primer_nombre ?? '') . ' ' .
                ($this->segundo_nombre ?? '') . ' ' .
                ($this->primer_apellido ?? '') . ' ' .
                ($this->segundo_apellido ?? '')
        );
    }
}
