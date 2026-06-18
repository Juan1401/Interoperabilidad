<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla iso_3166_1.
 * Almacena los códigos de países ISO 3166-1.
 *
 * Tipos de código (code_type):
 *   - alpha2: Código de 2 letras (CO, US, AR)
 *   - alpha3: Código de 3 letras (COL, USA, ARG)
 *   - numeric: Código numérico (170, 840, 032)
 */
class Iso31661 extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'iso_3166_1';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'display',
        'code_type',
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
