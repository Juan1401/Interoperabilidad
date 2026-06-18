<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColombianOrganizationIdentifier extends Model
{
    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'ihce.colombian_organization_identifiers';

    /**
     * Los atributos que son asignables.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'display',
        'active',
    ];
}
