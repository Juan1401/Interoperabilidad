<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIhceControlEnviosLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ihce_control_envios_logs', function (Blueprint $table) {
            $table->bigIncrements('log_id')->comment('Identificador único secuencial del log.');

            $table->unsignedBigInteger('envio_id')->comment('Relación con la tabla maestra ihce_control_envios.');
            $table->foreign('envio_id', 'fk_control_envios_logs_envio')->references('envio_id')->on('ihce_control_envios')->onDelete('restrict');

            $table->timestamp('fecha_evento')->useCurrent()->comment('Fecha exacta del evento.');

            // FK to public.system_usuarios
            $table->integer('usuario_id')->nullable()->comment('Usuario que ejecutó la acción.');
            // $table->foreign('usuario_id')->references('usuario_id')->on('public.system_usuarios')->onDelete('restrict');

            // FK to ihce_cat_acciones_log
            $table->integer('accion_log_id')->unsigned()->nullable()->comment('Tipo de acción, foráneo a ihce_cat_acciones_log.');
            $table->foreign('accion_log_id')->references('id')->on('ihce_cat_acciones_log')->onDelete('restrict');

            $table->jsonb('json_enviado')->nullable()->comment('Payload JSON enviado (para depuración).');
            $table->jsonb('mensaje_respuesta')->nullable()->comment('Respuesta técnica del servidor externo. JSON');

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
        Schema::dropIfExists('ihce_control_envios_logs');
    }
}
