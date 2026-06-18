<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoIdPaciente extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'public.tipos_id_pacientes';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'tipo_id_paciente';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
}
