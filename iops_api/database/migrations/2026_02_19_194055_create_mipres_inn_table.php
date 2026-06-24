<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la tabla para almacenar los códigos INN (Denominación Común Internacional)
     * del sistema MIPRES (Mi Prescripción).
     *
     * Fuente: definitions_hl7_json/HL7_definicion/CodeSystem-MipresINN.csv
     *
     * Estructura del CSV:
     *   - Columna 1: code (código numérico del INN)
     *   - Columna 2: display (nombre del principio activo)
     */
    public function up(): void
    {
        Schema::create('mipres_inn', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()
                ->comment('Código INN del principio activo en MIPRES. Ej: 1, 10001');
            $table->text('display')
                ->comment('Nombre del principio activo (INN). Ej: HIDRALAZINA, NUSINERSEN');
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
        Schema::dropIfExists('mipres_inn');
    }
};
