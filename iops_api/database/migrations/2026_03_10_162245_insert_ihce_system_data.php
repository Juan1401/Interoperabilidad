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
        // Llamar implícitamente al seeder para garantizar ejecución en despliegue
        \Illuminate\Support\Facades\Artisan::call('db:seed', [
            '--class' => 'IhceSystemDataSeeder'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
