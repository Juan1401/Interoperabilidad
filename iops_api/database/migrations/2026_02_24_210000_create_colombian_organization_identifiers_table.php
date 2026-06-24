<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateColombianOrganizationIdentifiersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ihce.colombian_organization_identifiers', function (Blueprint $table) {
            $table->id()->comment('Identificador único autoincremental');
            $table->string('code', 20)->unique()->comment('Código del tipo de identificador (ej: NIT, CodigoPrestador)');
            $table->string('display')->comment('Descripción legible del tipo de identificador');
            $table->boolean('active')->default(true)->comment('Indica si el tipo de identificador está activo');
            $table->timestamps();

            $table->comment('Tabla para tipos de identificadores de organizaciones en Colombia (HL7 v0.7.1)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ihce.colombian_organization_identifiers');
    }
}
