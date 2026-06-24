<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDivipolaTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema ihce is already created and used in previous migrations

        Schema::create('ihce.departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()->comment('Código DIVIPOLA del departamento (ej: 05)');
            $table->string('display')->comment('Nombre del departamento');
            $table->boolean('active')->default(true)->comment('Indica si el registro está activo');
            $table->timestamps();

            $table->comment('Tabla de departamentos de Colombia (DIVIPOLA)');
        });

        Schema::create('ihce.municipalities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id');
            $table->string('code', 10)->unique()->comment('Código DIVIPOLA del municipio (ej: 05001)');
            $table->string('display')->comment('Nombre del municipio');
            $table->text('definition')->nullable()->comment('Descripción adicional o metadatos (ej: Latitud/Longitud)');
            $table->boolean('active')->default(true)->comment('Indica si el registro está activo');
            $table->timestamps();

            $table->foreign('department_id')
                ->references('id')
                ->on('ihce.departments')
                ->onDelete('cascade');

            $table->comment('Tabla de municipios de Colombia relacionados por departamento (DIVIPOLA)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ihce.municipalities');
        Schema::dropIfExists('ihce.departments');
    }
}
