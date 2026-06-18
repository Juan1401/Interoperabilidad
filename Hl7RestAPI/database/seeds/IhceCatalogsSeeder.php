<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IhceCatalogsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // ihce_cat_tipos_rda
        $tiposRda = [
            ['codigo' => 'RDA_PACIENTE', 'nombre' => 'RDA Paciente'],
            ['codigo' => 'RDA_URGENCIAS', 'nombre' => 'RDA Urgencias'],
            ['codigo' => 'RDA_CONSULTA_EXTERNA', 'nombre' => 'RDA Consulta Externa'],
            ['codigo' => 'RDA_HOSPITALIZACION', 'nombre' => 'RDA Hospitalizacion'],
        ];

        foreach ($tiposRda as $rda) {
            DB::table('ihce_cat_tipos_rda')->updateOrInsert(
                ['codigo' => $rda['codigo']],
                ['nombre' => $rda['nombre'], 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // ihce_cat_estados_envio
        $estadosEnvio = [
            ['nombre' => 'PENDIENTE'],
            ['nombre' => 'EXITOSO'],
            ['nombre' => 'FALLIDO'],
            ['nombre' => 'RECHAZADO'],
        ];

        foreach ($estadosEnvio as $estado) {
            DB::table('ihce_cat_estados_envio')->updateOrInsert(
                ['nombre' => $estado['nombre']],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }

        // ihce_cat_acciones_log
        $accionesLog = [
            ['nombre' => 'ENVIO_MANUAL'],
            ['nombre' => 'REINTENTO_AUTOMATICO'],
            ['nombre' => 'CIERRE_SISTEMA'],
        ];

        foreach ($accionesLog as $accion) {
            DB::table('ihce_cat_acciones_log')->updateOrInsert(
                ['nombre' => $accion['nombre']],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
