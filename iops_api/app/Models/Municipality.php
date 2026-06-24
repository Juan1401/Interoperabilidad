<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    protected $table = 'ihce.municipalities';

    protected $fillable = [
        'department_id',
        'code',
        'display',
        'definition',
        'active',
    ];

    /**
     * Get the department that owns the municipality.
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
