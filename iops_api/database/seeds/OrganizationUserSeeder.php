<?php

use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\User;

class OrganizationUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 1. Crear o actualizar la organización de prueba con datos reales de Club Noel
        $organization = Organization::updateOrCreate(
            ['nit' => '890303093'],
            [
                'razon_social' => 'FUNDACION CLINICA INFANTIL CLUB NOEL',
                'codigo_habilitacion' => '760010536201'
            ]
        );

        // 2. Buscar al primer usuario y actualizarle sus datos
        $user = User::first();
        if ($user) {
            $user->update([
                'organization_id' => $organization->id,
                'tipo_documento' => 'CC',
                'numero_documento' => '123456789',
                'apellidos' => 'Médico Prueba',
                'especialidad_codigo' => '389'
            ]);
            $this->command->info('Usuario asignado a la empresa de prueba correctamente.');
        } else {
            $this->command->error('No se encontró ningún usuario en la base de datos. Ejecute los seeders base primero si es necesario.');
        }
    }
}
