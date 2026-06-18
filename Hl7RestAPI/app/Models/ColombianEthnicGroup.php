<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla colombian_ethnic_group.
 * Almacena los grupos étnicos colombianos según el CodeSystem de MinSalud.
 *
 * URL: https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianEthnicGroup
 *
 * Códigos:
 *   1 - Indigena
 *   2 - ROM (Gitano)
 *   3 - Raizal (Archipielago San Andrés y Providencia)
 *   4 - Palenquero de San Basilio
 *   5 - Negro(a) o mulato(a) o afrocolombiano(a) o afrodescendiente
 *   6 - Otras etnias
 */
class ColombianEthnicGroup extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'colombian_ethnic_group';

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
