<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la tabla para almacenar los grupos étnicos colombianos (ColombianEthnicGroup).
     *
     * Fuente: definitions_hl7_json/HL7_definicion/CodeSystem-ColombianEthnicGroup.json
     * URL: https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianEthnicGroup
     * Total: 6 códigos
     */
    public function up(): void
    {
        Schema::create('colombian_ethnic_group', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()
                ->comment('Código del grupo étnico. Ej: 1, 2, 3...');
            $table->string('display', 255)
                ->comment('Nombre del grupo étnico. Ej: Indigena, ROM (Gitano)');
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
        Schema::dropIfExists('colombian_ethnic_group');
    }
};
