<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profesional extends Model
{
    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'public.profesionales';

    /**
     * Indica si el ID es autoincremental.
     * En este caso es una clave compuesta (tipo_id_tercero, tercero_id).
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indica si el modelo debe tener timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Los atributos que son asignables.
     *
     * @var array
     */
    protected $fillable = [
        'tipo_id_tercero',
        'tercero_id',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
    ];
}
