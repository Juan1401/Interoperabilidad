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
        Schema::create('ihce.ciuo_88_ac', function (Blueprint $table) {
            $table->id()->comment('Primary key autonumerica intera para relaciones foraneas relacionales');
            $table->string('code', 50)->unique()->comment('Código de la Ocupación CIUO-88 A.C. (Llave Natural FHIR)');
            $table->text('display')->comment('Descripción o nombre de la ocupación/categoría');
            $table->string('parent_code', 50)->nullable()->index()->comment('Código padre para establecer niveles categóricos y jerarquía');
            $table->smallInteger('level')->default(0)->comment('Nivel jerárquico del código (1=Gran Grupo, 2=Subgrupo, etc.)');
            $table->timestamps();
        });
        
        // Agregar comentario a la tabla
        \Illuminate\Support\Facades\DB::statement("COMMENT ON TABLE ihce.ciuo_88_ac IS 'Catálogo Estándar Clasificación Internacional Uniforme de Ocupaciones Adaptada para Colombia (CIUO-88 A.C.)'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ihce.ciuo_88_ac');
    }
};
