<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la tabla para almacenar los tipos de identificadores de personas en Colombia.
     *
     * Fuente: definitions_hl7_json/HL7_definicion/CodeSystem-ColombianPersonIdentifier.json
     * URL: https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianPersonIdentifier
     * Total: 19 códigos
     */
    public function up(): void
    {
        Schema::create('colombian_person_identifier', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()
                ->comment('Código del tipo de identificación. Ej: CC, TI, PA, CE');
            $table->string('display', 255)
                ->comment('Descripción del tipo de identificación. Ej: Cédula ciudadanía, Pasaporte');
            $table->text('definition')->nullable()
                ->comment('Definición detallada del tipo de identificación');
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
        Schema::dropIfExists('colombian_person_identifier');
    }
};
