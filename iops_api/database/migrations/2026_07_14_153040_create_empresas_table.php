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
        Schema::create('ihce.organizations', function (Blueprint $table) {
            $table->id();
            $table->string('nit')->unique();
            $table->string('razon_social');
            $table->string('codigo_habilitacion', 12);
            $table->string('client_id')->nullable();
            $table->string('client_secret')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ihce.organizations');
    }
};
