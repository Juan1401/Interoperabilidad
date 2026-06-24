<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla maestra de catálogos HL7 (CodeSystem).
 * Almacena la información general de cada sistema de codificación.
 *
 * Ejemplo de registro:
 *   name: ColombianDiagnosisRole
 *   url: https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDiagnosisRole
 *   title: CodeSystem: Colombian Diagnosis Role
 *   status: active
 */
class Hl7Catalog extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     *
     * @var string
     */
    protected $table = 'hl7_catalogs';

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array
     */
    protected $fillable = [
        'resource_type',
        'name',
        'language',
        'url',
        'version',
        'title',
        'status',
        'experimental',
        'date',
        'publisher',
        'description',
        'purpose',
        'copyright',
        'case_sensitive',
        'content',
        'count',
    ];

    /**
     * Los atributos que deben ser casteados.
     *
     * @var array
     */
    protected $casts = [
        'experimental'   => 'boolean',
        'case_sensitive' => 'boolean',
        'date'           => 'date',
        'count'          => 'integer',
    ];

    /**
     * Relación uno a muchos con Hl7CatalogItem (conceptos del catálogo).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items()
    {
        return $this->hasMany(Hl7CatalogItem::class, 'hl7_catalog_id');
    }

    /**
     * Busca un catálogo por su nombre único.
     *
     * @param string $name Nombre del catálogo (ej: ColombianTechModality)
     * @return self|null
     */
    public static function findByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    /**
     * Obtiene un ítem específico por código dentro de este catálogo.
     *
     * @param string $code Código del concepto (ej: 01)
     * @return Hl7CatalogItem|null
     */
    public function getItemByCode(string $code): ?Hl7CatalogItem
    {
        return $this->items()->where('code', $code)->first();
    }
}
