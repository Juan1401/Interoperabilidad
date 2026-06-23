<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Hl7PharmacologicalCatalogsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::disableQueryLog();

        $this->command->info('Iniciando carga de Catálogos Farmacológicos...');

        $files = [
            'fhir_cums' => 'CodeSystem-CUMS.json',
            'fhir_ium'  => 'CodeSystem-IUM.json',
            'fhir_vad'  => 'CodeSystem-VAD.json',
            'fhir_umm'  => 'CodeSystem-UMM.json'
        ];

        foreach ($files as $table => $filename) {
            $path = base_path('definitions_hl7_json/HL7_definicion/' . $filename);
            
            if (!file_exists($path)) {
                $this->command->warn("Archivo no encontrado: {$filename}");
                continue;
            }

            $this->command->info("Procesando {$filename} para la tabla {$table}...");
            
            $json = file_get_contents($path);
            $data = json_decode($json, true);

            if (!isset($data['concept'])) {
                $this->command->warn("No se encontraron conceptos en {$filename}");
                continue;
            }

            DB::beginTransaction();
            try {
                $batch = [];
                $count = 0;
                
                foreach ($data['concept'] as $concept) {
                    $batch[] = [
                        'code' => $concept['code'],
                        'display' => $concept['display'],
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    if (count($batch) >= 1000) {
                        DB::table($table)->upsert($batch, ['code'], ['display', 'updated_at']);
                        $count += count($batch);
                        $batch = [];
                    }
                }

                if (!empty($batch)) {
                    DB::table($table)->upsert($batch, ['code'], ['display', 'updated_at']);
                    $count += count($batch);
                }

                DB::commit();
                $this->command->info("Insertados/Actualizados {$count} registros en {$table}.");
            } catch (\Exception $e) {
                DB::rollBack();
                $this->command->error("Error procesando {$filename}: " . $e->getMessage());
            }
        }
    }
}
