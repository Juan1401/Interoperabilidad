<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIhceCatalogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ihce_cat_tipos_rda', function (Blueprint $table) {
            $table->increments('id');
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 100);
            $table->timestamps();
            $table->comment('Catálogo de tipos de RDA (ej: RDA Paciente, RDA Urgencias).');
        });

        Schema::create('ihce_cat_estados_envio', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 50)->unique();
            $table->timestamps();
            $table->comment('Catálogo de estados de envío (ej: PENDIENTE, EXITOSO, FALLIDO, RECHAZADO).');
        });

        Schema::create('ihce_cat_acciones_log', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 50)->unique();
            $table->timestamps();
            $table->comment('Catálogo de acciones de log (ej: ENVIO_MANUAL, REINTENTO_AUTOMATICO, CIERRE_SISTEMA).');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ihce_cat_acciones_log');
        Schema::dropIfExists('ihce_cat_estados_envio');
        Schema::dropIfExists('ihce_cat_tipos_rda');
    }
}
