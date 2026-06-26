<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class RdaDocument extends Model
{
    use SoftDeletes;

    protected $table = 'rda_documents';
    
    // Le decimos a Laravel que el ID no es numérico autoincremental
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'document_type',
        'status',
        'form_payload',
        'fhir_bundle_generated',
        'minsalud_response'
    ];

    // Laravel convertirá automáticamente el JSON de Postgres a un Array de PHP
    protected $casts = [
        'form_payload' => 'array',
        'fhir_bundle_generated' => 'array',
        'minsalud_response' => 'array',
    ];

    // Generar UUID automáticamente al crear
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // Relación con el usuario
    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }
}