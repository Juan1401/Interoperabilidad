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
        // Catálogo de Calificaciones RETHUS
        Schema::create('fhir_rethus_qualification', function (Blueprint $table) {
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
        Schema::dropIfExists('fhir_rethus_qualification');
    }
};
