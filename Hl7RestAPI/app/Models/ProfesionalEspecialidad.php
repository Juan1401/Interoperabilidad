<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfesionalEspecialidad extends Model
{
    protected $table = 'public.profesionales_especialidades';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    // Composite primary key handling is tricky in Eloquent, 
    // relying on relationships is often better if we don't need to save/update directly via Model::find
    protected $primaryKey = null;

    protected $fillable = [
        'tercero_id',
        'tipo_id_tercero',
        'especialidad',
    ];

    public function especialidadDetail()
    {
        return $this->belongsTo(Especialidad::class, 'especialidad', 'especialidad');
    }
}
