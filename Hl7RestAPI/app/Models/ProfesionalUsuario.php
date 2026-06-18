<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfesionalUsuario extends Model
{
    protected $table = 'public.profesionales_usuarios';
    protected $primaryKey = 'usuario_id';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'tipo_tercero_id',
        'tercero_id',
    ];

    public function profesional()
    {
        return $this->belongsTo(Profesional::class, 'tercero_id', 'tercero_id')
            ->where('tipo_id_tercero', $this->tipo_tercero_id);
    }

    public function profesionalEspecialidades()
    {
        return $this->hasMany(ProfesionalEspecialidad::class, 'tercero_id', 'tercero_id');
    }
}
