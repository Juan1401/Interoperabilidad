<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RdaAuditLog extends Model
{
    protected $table = 'ihce.rda_audit_logs';

    protected $fillable = [
        'user_id',
        'patient_document_type',
        'patient_document_number',
        'tipo_rda_id',
        'rda_id',
    ];
}
