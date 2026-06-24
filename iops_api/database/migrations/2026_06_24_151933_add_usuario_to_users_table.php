<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUsuarioToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('ihce.users', function (Blueprint $table) {
            $table->string('usuario')->unique();
        });
    }

    public function down()
    {
        Schema::table('ihce.users', function (Blueprint $table) {
            $table->dropColumn('usuario');
        });
    }
}
