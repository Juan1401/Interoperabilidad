<?php

namespace App\Models\Ihce;

use Illuminate\Database\Eloquent\Model;

class AuditoriaAccesoLink extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ihce.auditoria_accesos_links';

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
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'uuid';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'usuario_id',
        'ip',
        'estado'
    ];
}
