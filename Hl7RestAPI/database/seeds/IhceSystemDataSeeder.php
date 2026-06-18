<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IhceSystemDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Permisos para modulo BioEstadistica
        try {
            \Illuminate\Support\Facades\DB::statement("
                INSERT INTO system_modulos_opciones_permisos
                VALUES ('BioEstadistica', 'app','sw_envio_ihce', 'PERMITIR ENVIO DE IHCE A MINSALUD', 0);
            ");
        } catch (\Exception $e) {
            // Ignorar duplicados
        }

        try {
            \Illuminate\Support\Facades\DB::statement("
                INSERT INTO system_modulos_opciones_permisos
                VALUES ('BioEstadistica', 'app','sw_visor_ihce', 'PERMITIR VISOR DE IHCE A MINSALUD', 0);
            ");
        } catch (\Exception $e) {
            // Ignorar duplicados
        }

        // 2. Variables para modulo BioEstadistica
        try {
            \Illuminate\Support\Facades\DB::statement("
                INSERT INTO public.system_modulos_variables
                (modulo, modulo_tipo, variable, valor, descripcion)
                VALUES('BioEstadistica', 'app', 'IHCE_url_frontend', 'http://localhost:4202', 'Url del validador Rips Docker');
            ");
        } catch (\Exception $e) {
            // Ignorar duplicados
        }

        try {
            \Illuminate\Support\Facades\DB::statement("
                INSERT INTO public.system_modulos_variables
                (modulo, modulo_tipo, variable, valor, descripcion)
                VALUES('BioEstadistica', 'app', 'IHCE_key_secreta', '8pZ9mQ2nX5vL7wR1tB4yK6jH3dW0sG9f', 'Variable para encriptar');
            ");
        } catch (\Exception $e) {
            // Ignorar duplicados
        }

        try {
            \Illuminate\Support\Facades\DB::statement("
                INSERT INTO public.system_modulos_variables
                (modulo, modulo_tipo, variable, valor, descripcion)
                VALUES('BioEstadistica', 'app', 'IHCE_sw_envio_epicrisis', '1', 'valor = 1 (Envia Epicrisis generada apartir de Ingreso) - valor = 0 (Envia una texto plano en base64 predeterminado y reducido para evitar timeouts en el envio del ministerio.)');
            ");
        } catch (\Exception $e) {
            // Ignorar duplicados
        }

    }
}
