<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Renombra la tabla ihce.empresas a ihce.organizations y actualiza
     * la columna FK empresa_id en ihce.users a organization_id.
     */
    public function up(): void
    {
        // 1. Eliminar la constraint de llave foránea antes de renombrar
        Schema::table('ihce.users', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
        });

        // 2. Renombrar la tabla usando SQL nativo (Schema::rename no soporta esquemas calificados)
        DB::statement('ALTER TABLE ihce.empresas RENAME TO organizations');

        // 3. Renombrar la columna FK en users
        Schema::table('ihce.users', function (Blueprint $table) {
            $table->renameColumn('empresa_id', 'organization_id');
        });

        // 4. Re-crear la constraint de llave foránea con el nuevo nombre
        Schema::table('ihce.users', function (Blueprint $table) {
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('ihce.organizations')
                  ->nullOnDelete();
        });
    }

    /**
     * Revierte el renombramiento: organizations → empresas, organization_id → empresa_id.
     */
    public function down(): void
    {
        // 1. Eliminar la nueva constraint
        Schema::table('ihce.users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });

        // 2. Revertir el nombre de la tabla
        DB::statement('ALTER TABLE ihce.organizations RENAME TO empresas');

        // 3. Revertir el nombre de la columna
        Schema::table('ihce.users', function (Blueprint $table) {
            $table->renameColumn('organization_id', 'empresa_id');
        });

        // 4. Re-crear la constraint original
        Schema::table('ihce.users', function (Blueprint $table) {
            $table->foreign('empresa_id')
                  ->references('id')
                  ->on('ihce.empresas')
                  ->nullOnDelete();
        });
    }
};
