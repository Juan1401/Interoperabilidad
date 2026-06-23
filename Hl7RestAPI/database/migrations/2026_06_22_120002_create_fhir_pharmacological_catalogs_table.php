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
        // CUMS (Código Único de Medicamentos)
        Schema::create('fhir_cums', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->text('display');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('display');
        });

        // IUM (Identificador Único de Medicamento)
        Schema::create('fhir_ium', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->text('display');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('display');
        });

        // VAD (Vía de Administración)
        Schema::create('fhir_vad', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->text('display');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('display');
        });

        // UMM (Unidad de Medida de Medicamento)
        Schema::create('fhir_umm', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->text('display');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('display');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fhir_umm');
        Schema::dropIfExists('fhir_vad');
        Schema::dropIfExists('fhir_ium');
        Schema::dropIfExists('fhir_cums');
    }
};
