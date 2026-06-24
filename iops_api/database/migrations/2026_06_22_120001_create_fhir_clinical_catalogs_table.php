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
        // CIE-11 (Clasificación Internacional de Enfermedades 11)
        Schema::create('fhir_icd11co', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->text('display');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('display');
        });

        // CUPS (Clasificación Única de Procedimientos en Salud)
        Schema::create('fhir_cups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->text('display');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('display');
        });

        // Clase de Triage
        Schema::create('fhir_clase_triage', function (Blueprint $table) {
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
        Schema::dropIfExists('fhir_clase_triage');
        Schema::dropIfExists('fhir_cups');
        Schema::dropIfExists('fhir_icd11co');
    }
};
