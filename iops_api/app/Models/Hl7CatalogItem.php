<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla detalle de ítems del catálogo HL7.
 * Almacena los códigos individuales (conceptos) de cada sistema de codificación.
 *
 * Ejemplo de registro (ColombianDiagnosisRole):
 *   code: 8319008
 *   display: diagnóstico primario
 *   designation: [{"language":"es","use":{"system":"...","code":"display"},"value":"diagnóstico primario"}]
 */
class Hl7CatalogItem extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     *
     * @var string
     */
    protected $table = 'hl7_catalog_items';

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array
     */
    protected $fillable = [
        'hl7_catalog_id',
        'code',
        'display',
        'definition',
        'designation',
        'active',
    ];

    /**
     * Los atributos que deben ser casteados.
     *
     * @var array
     */
    protected $casts = [
        'active'      => 'boolean',
        'designation' => 'array',
    ];

    /**
     * Relación inversa con Hl7Catalog (catálogo padre).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function catalog()
    {
        return $this->belongsTo(Hl7Catalog::class, 'hl7_catalog_id');
    }

    /**
     * Busca un ítem por nombre de catálogo y código.
     *
     * @param string $catalogName Nombre del catálogo (ej: ColombianTechModality)
     * @param string $code Código del ítem (ej: 01)
     * @return self|null
     */
    public static function findByCatalogAndCode(string $catalogName, string $code): ?self
    {
        return static::whereHas('catalog', function ($query) use ($catalogName) {
            $query->where('name', $catalogName);
        })->where('code', $code)->first();
    }
}
