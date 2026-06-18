<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la tabla para almacenar los códigos ICD-10 Colombia (CIE-10).
     *
     * Fuente: definitions_hl7_json/HL7_definicion/CodeSystem-ICD10CO.json
     *
     * Estructura del JSON:
     *   - resourceType: "CodeSystem"
     *   - url: "http://hl7.org/fhir/sid/icd-10"
     *   - name: "ICD10CO"
     *   - content: "fragment" (subconjunto de códigos CIE-10 utilizados en Colombia)
     *   - concept[]: { code, display } (sin designation)
     */
    public function up(): void
    {
        Schema::create('icd10co', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()
                ->comment('Código CIE-10. Ej: A000, Z034, B99X');
            $table->text('display')
                ->comment('Descripción del diagnóstico. Ej: Colera debido a Vibrio cholerae 01, biotipo cholerae');
            $table->boolean('active')->default(true)
                ->comment('Indica si el código está activo');
            $table->timestamps();

            // Índice para búsquedas por texto
            $table->index('display');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('icd10co');
    }
};
