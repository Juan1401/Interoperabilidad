<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para los grupos de género colombianos.
 *
 * CodeSystem FHIR: https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianGenderGroup
 * Tabla: ihce.colombian_gender_group
 */
class ColombianGenderGroup extends Model
{
    protected $table = 'ihce.colombian_gender_group';

    protected $fillable = [
        'code',
        'display',
        'active',
    ];
}
