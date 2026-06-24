<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            'name' => 'admin',
            'email' => 'admin@fciclubnoel.com',
            'password' => Hash::make('S1N3RG1@S'),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Usar updateOrCreate para hacer el seeder idempotente
        // Si el usuario con este email ya existe, se actualiza; si no, se crea
        DB::table('users')->updateOrInsert(
            ['email' => 'admin@fciclubnoel.com'], // Condición de búsqueda
            [
                'name' => 'admin',
                'password' => Hash::make('S1N3RG1@S'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
