<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOptimizedIndexToIngresos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        try {
            // Índices sugeridos para optimizar la consulta de RDAs
            \Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS ingresos_fecha_ingreso_index ON public.ingresos (fecha_ingreso)');
            \Illuminate\Support\Facades\DB::statement('CREATE INDEX IF NOT EXISTS ingresos_paciente_id_tipo_paciente_index ON public.ingresos (paciente_id, tipo_id_paciente)');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Tabla public.ingresos no encontrada, omitiendo creación de índices.");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('public.ingresos', function (Blueprint $table) {
            $table->dropIndex('ingresos_fecha_ingreso_index');
            $table->dropIndex('ingresos_paciente_id_tipo_paciente_index');
        });
    }
}
