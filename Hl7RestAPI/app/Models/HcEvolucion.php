<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HcEvolucion extends Model
{
    protected $table = 'public.hc_evoluciones';
    protected $primaryKey = 'evolucion_id';
    public $timestamps = false; // Assuming false based on typical schema, can be adjusted

    protected $fillable = [
        'evolucion_id',
        'ingreso',
        'usuario_id',
        'estado',
    ];

    public function profesionalUsuario()
    {
        return $this->belongsTo(ProfesionalUsuario::class, 'usuario_id', 'usuario_id');
    }

    public function ingresoRel()
    {
        return $this->belongsTo(Ingresos::class, 'ingreso', 'ingreso');
    }
}
