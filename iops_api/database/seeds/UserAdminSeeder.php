<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserAdminSeeder extends Seeder
{
    public function run()
    {
        DB::table('ihce.users')->updateOrInsert(
            ['usuario' => 'admin'],
            [
                'name' => 'Administrador',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('S1N3RG1@S'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
