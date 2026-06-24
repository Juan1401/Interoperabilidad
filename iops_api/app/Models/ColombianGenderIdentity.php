<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla colombian_gender_identity.
 * Almacena las identidades de género colombianas según el CodeSystem de MinSalud.
 *
 * URL: https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianGenderIdentity
 *
 * Códigos:
 *   01 - Masculino
 *   02 - Femenino
 *   03 - Transgénero
 *   04 - Neutro
 *   05 - No lo declara
 */
class ColombianGenderIdentity extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'colombian_gender_identity';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'display',
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
