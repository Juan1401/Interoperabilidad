<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingresos extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     *
     * @var string
     */
    protected $table = 'public.ingresos';

    /**
     * Clave primaria de la tabla.
     *
     * @var string
     */
    protected $primaryKey = 'ingreso';

    /**
     * Indica si la clave primaria es autoincremental.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Indica si el modelo usa timestamps (created_at, updated_at).
     *
     * @var bool
     */
    public $timestamps = false; // Asumo false por el código anterior, ajustar si es necesario

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array
     */
    protected $fillable = [
        'ingreso',
        'paciente_id',
        'tipo_id_paciente',
        'fecha_ingreso',
        // Agrega aquí los campos que necesites
    ];

    /**
     * Los atributos que deben ser casteados.
     *
     * @var array
     */
    protected $casts = [
        'ingreso' => 'integer',
        'fecha_ingreso' => 'datetime',
    ];

    /**
     * Relación con el modelo Pacientes.
     * Nota: Se usa una relación manual debido a la clave compuesta en Pacientes.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function paciente()
    {
        return $this->belongsTo(Pacientes::class, 'paciente_id', 'paciente_id')
            ->where('tipo_id_paciente', $this->tipo_id_paciente);
    }

    /**
     * Relación con HcEvolucion.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function hcEvoluciones()
    {
        return $this->hasMany(HcEvolucion::class, 'ingreso', 'ingreso');
    }
}
