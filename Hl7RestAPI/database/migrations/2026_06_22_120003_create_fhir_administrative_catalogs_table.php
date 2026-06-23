<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vía de Ingreso
        Schema::create('fhir_via_ingreso', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->text('display');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Entorno de Atención
        Schema::create('fhir_entorno_atencion', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->text('display');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // RIPS Causa Externa Version 2
        Schema::create('fhir_rips_causa_externa', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->text('display');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // RIPS Finalidad Consulta Version 2
        Schema::create('fhir_rips_finalidad_consulta', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->text('display');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fhir_rips_finalidad_consulta');
        Schema::dropIfExists('fhir_rips_causa_externa');
        Schema::dropIfExists('fhir_entorno_atencion');
        Schema::dropIfExists('fhir_via_ingreso');
    }
};
