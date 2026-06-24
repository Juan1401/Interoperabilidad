<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateColombianGenderGroupTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la tabla ihce.colombian_gender_group basada en el CodeSystem FHIR:
     * https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianGenderGroup
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ihce.colombian_gender_group', function (Blueprint $table) {
            $table->id()->comment('Identificador interno autoincremental');
            $table->string('code', 10)->unique()->comment('Código del grupo de género (ej: 01=Hombre, 02=Mujer, 03=Indeterminado o Intersexual)');
            $table->string('display')->comment('Descripción legible del grupo de género (es-CO)');
            $table->boolean('active')->default(true)->comment('Indica si el registro está activo en el CodeSystem');
            $table->timestamps();

            $table->comment(
                'Tabla de grupos de género colombianos (ColombianGenderGroup). ' .
                    'CodeSystem: https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianGenderGroup. ' .
                    'Fuente: MinSalud Colombia - HL7 FHIR RDA v0.7.1'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ihce.colombian_gender_group');
    }
}
