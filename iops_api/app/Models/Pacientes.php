<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pacientes extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     *
     * @var string
     */
    protected $table = 'public.pacientes';

    /**
     * Clave primaria de la tabla.
     * Laravel no soporta claves compuestas nativamente, por lo que se define una pseudo-clave
     * o se maneja la consistencia manualmente. Para este caso, usamos paciente_id.
     *
     * @var string
     */
    protected $primaryKey = 'paciente_id';

    /**
     * Indica si la clave primaria es autoincremental.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Tipo de la clave primaria.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indica si el modelo usa timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array
     */
    protected $fillable = [
        'paciente_id',
        'tipo_id_paciente',
        'primer_apellido',
        'segundo_apellido',
        'primer_nombre',
        'segundo_nombre',
        'fecha_nacimiento',
        'sexo_id',
        'residencia_direccion',
        'residencia_telefono',
        'zona_residencia',
        'tipo_pais_id',
        'tipo_dpto_id',
        'tipo_mpio_id',
        'nacionalidad',
        // Campos FHIR Minsalud – agregados por migración 2026_03_17
        'codigo_etnia',
        'codigo_discapacidad',
        'municipio_residencia_divipola_id',
    ];
}
