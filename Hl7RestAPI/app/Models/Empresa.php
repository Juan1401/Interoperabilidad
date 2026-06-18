<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $table = 'public.empresas';
    protected $primaryKey = 'empresa_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'empresa_id',
        'tipo_id_tercero',
        'id',
        'razon_social',
        'representante_legal',
        'codigo_sgsss',
        'tipo_pais_id',
        'tipo_dpto_id',
        'tipo_mpio_id',
        'direccion',
        'telefonos',
        'fax',
        'email',
        'codigo_sgsss_ips',
        'digito_verificacion'
    ];
}
