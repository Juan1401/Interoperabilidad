<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'ihce.departments';

    protected $fillable = [
        'code',
        'display',
        'active',
    ];

    /**
     * Get the municipalities for the department.
     */
    public function municipalities()
    {
        return $this->hasMany(Municipality::class, 'department_id');
    }
}
