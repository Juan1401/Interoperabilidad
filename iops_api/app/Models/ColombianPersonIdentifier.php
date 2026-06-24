<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla colombian_person_identifier.
 * Almacena los tipos de identificadores de personas en Colombia según MinSalud.
 *
 * URL: https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianPersonIdentifier
 */
class ColombianPersonIdentifier extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'colombian_person_identifier';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'display',
        'definition',
        'active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'active' => 'boolean',
    ];
}
