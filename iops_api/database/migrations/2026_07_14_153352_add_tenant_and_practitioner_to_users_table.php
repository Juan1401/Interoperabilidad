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
            $table->foreignId('organization_id')->nullable()->constrained('ihce.organizations');
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
            $table->dropForeign(['organization_id']);
            $table->dropColumn([
                'organization_id',
                'tipo_documento',
                'numero_documento',
                'apellidos',
                'especialidad_codigo'
            ]);
        });
    }
};
