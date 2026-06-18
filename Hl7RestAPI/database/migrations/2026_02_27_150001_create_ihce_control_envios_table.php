<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIhceControlEnviosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ihce_control_envios', function (Blueprint $table) {
            $table->bigIncrements('envio_id')->comment('Identificador único autoincremental de la transacción de interoperabilidad.');

            // FK to public.ingresos
            $table->integer('ingreso_id')->comment('Llave foránea numérica que referencia al ingreso en el sistema principal (INTEGER).');
            $table->foreign('ingreso_id')->references('ingreso')->on('public.ingresos')->onDelete('restrict');

            // FK to public.hc_evoluciones
            $table->integer('evolucion_id')->nullable()->comment('Llave foránea numérica que referencia a la evolución (INTEGER).');
            $table->foreign('evolucion_id')->references('evolucion_id')->on('public.hc_evoluciones')->onDelete('restrict');

            // FK to ihce_cat_tipos_rda
            $table->integer('tipo_rda_id')->unsigned()->comment('ID foráneo al catálogo de tipos de RDA.');
            $table->foreign('tipo_rda_id')->references('id')->on('ihce_cat_tipos_rda')->onDelete('restrict');

            // FK to ihce_cat_estados_envio
            $table->integer('estado_envio_id')->unsigned()->comment('ID foráneo al catálogo de estados de envío.');
            $table->foreign('estado_envio_id')->references('id')->on('ihce_cat_estados_envio')->onDelete('restrict');

            $table->timestamp('fecha_ultimo_intento')->nullable()->comment('Timestamp del último intento de envío.');
            $table->integer('intentos_realizados')->default(0)->comment('Contador de intentos de transmisión.');
            $table->string('codigo_respuesta_http', 10)->nullable()->comment('Código HTTP devuelto por la API (200, 400, 500).');

            $table->unique(['ingreso_id', 'tipo_rda_id'], 'uk_ingreso_rda');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ihce_control_envios');
    }
}
