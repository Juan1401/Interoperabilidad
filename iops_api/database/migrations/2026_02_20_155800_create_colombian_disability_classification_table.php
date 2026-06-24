<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la tabla para almacenar la clasificación de discapacidades colombianas.
     *
     * Fuente: definitions_hl7_json/HL7_definicion/CodeSystem-ColombianDisabilityClassification.json
     * URL: https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDisabilityClassification
     * Total: 8 códigos (01–08)
     */
    public function up(): void
    {
        Schema::create('colombian_disability_classification', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()
                ->comment('Código de discapacidad con ceros a la izquierda. Ej: 01, 02, ..., 08');
            $table->string('display', 255)
                ->comment('Descripción en español. Ej: Discapacidad física, Sin discapacidad');
            $table->string('display_en', 255)->nullable()
                ->comment('Descripción en inglés. Ej: Physical disability, Without disability');
            $table->boolean('active')->default(true)
                ->comment('Indica si el código está activo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colombian_disability_classification');
    }
};
