<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la tabla para almacenar las identidades de género colombianas.
     *
     * Fuente: definitions_hl7_json/HL7_definicion/CodeSystem-ColombianGenderIdentity.json
     * URL: https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianGenderIdentity
     * Total: 5 códigos (01–05)
     */
    public function up(): void
    {
        Schema::create('colombian_gender_identity', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()
                ->comment('Código de identidad de género con ceros a la izquierda. Ej: 01, 02, ..., 05');
            $table->string('display', 100)
                ->comment('Nombre de la identidad de género. Ej: Masculino, Femenino, Transgénero');
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
        Schema::dropIfExists('colombian_gender_identity');
    }
};
