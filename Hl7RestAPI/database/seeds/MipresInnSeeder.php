<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para la tabla mipres_inn (Denominación Común Internacional - MIPRES)
 *
 * Pobla la tabla mipres_inn con los códigos INN del sistema MIPRES
 * (Mi Prescripción) del Ministerio de Salud de Colombia.
 *
 * Fuente: definitions_hl7_json/HL7_definicion/CodeSystem-MipresINN.csv
 */
class MipresInnSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $csvFile = __DIR__ . '/CodeSystem-MipresINN.csv';

        if (!file_exists($csvFile)) {
            return;
        }

        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            return;
        }

        $batchSize = 500;
        $batch = [];
        $count = 0;

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            if (count($row) < 2) {
                continue;
            }

            $code = trim($row[0]);
            $display = trim($row[1]);

            $batch[] = [
                'code'       => $code,
                'display'    => $display,
                'active'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) >= $batchSize) {
                DB::table('mipres_inn')->upsert($batch, ['code'], ['display', 'updated_at']);
                $count += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table('mipres_inn')->upsert($batch, ['code'], ['display', 'updated_at']);
            $count += count($batch);
        }

        fclose($handle);
    }
}
