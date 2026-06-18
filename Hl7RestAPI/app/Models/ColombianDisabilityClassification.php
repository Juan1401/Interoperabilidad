<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla colombian_disability_classification.
 * Almacena la clasificación general de discapacidades colombianas según MinSalud.
 *
 * URL: https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDisabilityClassification
 *
 * Códigos:
 *   01 - Discapacidad física
 *   02 - Discapacidad visual
 *   03 - Discapacidad auditiva
 *   04 - Discapacidad intelectual
 *   05 - Discapacidad sicosocial
 *   06 - Sordoceguera
 *   07 - Discapacidad múltiple
 *   08 - Sin discapacidad
 */
class ColombianDisabilityClassification extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'colombian_disability_classification';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'display',
        'display_en',
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
