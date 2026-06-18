<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColombianResidenceZone extends Model
{
    protected $table = 'ihce.colombian_residence_zone';

    protected $fillable = [
        'code',
        'display',
        'active',
    ];
}
