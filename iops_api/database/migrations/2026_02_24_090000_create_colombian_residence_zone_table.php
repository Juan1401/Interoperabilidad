<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateColombianResidenceZoneTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ihce.colombian_residence_zone', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()->comment('Código de la zona de residencia (ej: 01, 02)');
            $table->string('display')->comment('Descripción de la zona (ej: Urbana, Rural)');
            $table->boolean('active')->default(true)->comment('Indica si el registro está activo');
            $table->timestamps();

            $table->comment('Tabla para tipos de zona de residencia en Colombia (Urbana/Rural)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ihce.colombian_residence_zone');
    }
}
