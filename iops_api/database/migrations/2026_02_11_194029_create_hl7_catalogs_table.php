<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea las tablas para almacenar los catálogos HL7 (CodeSystem) y sus conceptos.
     *
     * Tabla master (hl7_catalogs): Almacena la información general del CodeSystem
     *   - Corresponde a las propiedades raíz del JSON: resourceType, name, url, version,
     *     title, status, date, publisher, description, purpose, copyright, etc.
     *
     * Tabla detalle (hl7_catalog_items): Almacena cada concepto del CodeSystem
     *   - Corresponde a cada elemento del array "concept" del JSON: code, display, designation.
     */
    public function up(): void
    {
        // =====================================================================
        // TABLA MASTER: Catálogos HL7 (CodeSystem)
        // Almacena la información general de cada sistema de codificación.
        // Ejemplo: ColombianDiagnosisRole, ColombianTechModality, DIVIPOLA, etc.
        // =====================================================================
        Schema::create('hl7_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type')->default('CodeSystem')
                ->comment('Tipo de recurso FHIR (CodeSystem, ValueSet)');
            $table->string('name')->unique()
                ->comment('Identificador único del CodeSystem. Ej: ColombianDiagnosisRole');
            $table->string('language', 10)->nullable()
                ->comment('Idioma del recurso. Ej: es');
            $table->string('url')->nullable()
                ->comment('URL canónica. Ej: https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDiagnosisRole');
            $table->string('version', 20)->nullable()
                ->comment('Versión del CodeSystem. Ej: 0.7.1');
            $table->string('title')->nullable()
                ->comment('Título descriptivo. Ej: CodeSystem: Colombian Diagnosis Role');
            $table->string('status', 20)->default('active')
                ->comment('Estado: active, draft, retired');
            $table->boolean('experimental')->default(false)
                ->comment('Indica si es experimental');
            $table->date('date')->nullable()
                ->comment('Fecha de publicación. Ej: 2024-11-19');
            $table->string('publisher')->nullable()
                ->comment('Publicador. Ej: Ministerio de Salud y Protección Social de Colombia');
            $table->text('description')->nullable()
                ->comment('Descripción del sistema de codificación');
            $table->text('purpose')->nullable()
                ->comment('Propósito del sistema de codificación');
            $table->string('copyright')->nullable()
                ->comment('Derechos de autor. Ej: MinSalud Colombia, CC-BY-4.0 2021+');
            $table->boolean('case_sensitive')->default(false)
                ->comment('Sensible a mayúsculas/minúsculas');
            $table->string('content', 20)->nullable()
                ->comment('Tipo de contenido: complete, fragment, etc.');
            $table->integer('count')->nullable()
                ->comment('Cantidad total de conceptos en el CodeSystem');
            $table->timestamps();
        });

        // =====================================================================
        // TABLA DETALLE: Ítems/Conceptos del catálogo
        // Almacena cada código individual dentro de un CodeSystem.
        // Ejemplo para ColombianDiagnosisRole:
        //   code: "8319008", display: "diagnóstico primario"
        //   code: "398192003", display: "comorbilidades"
        // =====================================================================
        Schema::create('hl7_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hl7_catalog_id')
                ->comment('FK al catálogo padre en hl7_catalogs');
            $table->string('code')
                ->comment('Código del concepto. Ej: 8319008');
            $table->text('display')->nullable()
                ->comment('Texto de presentación. Ej: diagnóstico primario');
            $table->text('definition')->nullable()
                ->comment('Definición detallada del código (cuando aplica)');
            $table->json('designation')->nullable()
                ->comment('Designaciones del concepto: [{language, use: {system, code}, value}]');
            $table->boolean('active')->default(true)
                ->comment('Indica si el código está activo');
            $table->timestamps();

            // Relación con tabla master
            $table->foreign('hl7_catalog_id')
                ->references('id')
                ->on('hl7_catalogs')
                ->onDelete('cascade');

            // Un código no se puede repetir dentro del mismo catálogo
            $table->unique(['hl7_catalog_id', 'code']);
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hl7_catalog_items');
        Schema::dropIfExists('hl7_catalogs');
    }
};
