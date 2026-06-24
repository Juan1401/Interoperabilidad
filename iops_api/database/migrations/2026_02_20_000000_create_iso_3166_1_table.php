<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la tabla para almacenar los códigos de países según ISO 3166-1.
     * Incluye códigos de 2 letras (alpha2), 3 letras (alpha3) y numéricos.
     *
     * Fuente: definitions_hl7_json/HL7_definicion/CodeSystem-ISO31661.json
     * URL: https://fhir.minsalud.gov.co/rda/CodeSystem/ISO31661
     * Total: 750 códigos
     */
    public function up(): void
    {
        Schema::create('iso_3166_1', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()
                ->comment('Código ISO del país (AD, COL, 170)');
            $table->text('display')
                ->comment('Nombre del país. Ej: Colombia, Argentina');
            $table->string('code_type', 20)
                ->comment('Tipo de código: alpha2, alpha3, numeric');
            $table->boolean('active')->default(true)
                ->comment('Indica si el código está activo');
            $table->timestamps();

            $table->index('code_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iso_3166_1');
    }
};
