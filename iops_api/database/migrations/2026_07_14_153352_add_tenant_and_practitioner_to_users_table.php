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
        Schema::table('ihce.users', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->constrained('ihce.empresas');
            $table->string('tipo_documento', 5)->nullable();
            $table->string('numero_documento', 20)->nullable();
            $table->string('apellidos')->nullable();
            $table->string('especialidad_codigo', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ihce.users', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropColumn([
                'empresa_id',
                'tipo_documento',
                'numero_documento',
                'apellidos',
                'especialidad_codigo'
            ]);
        });
    }
};
