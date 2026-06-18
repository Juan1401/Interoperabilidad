<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para la tabla ICD10CO (CIE-10 Colombia)
 *
 * Pobla la tabla icd10co con los códigos de diagnóstico del
 * CodeSystem ICD10CO (Clasificación Internacional de Enfermedades v10).
 *
 * Fuente: definitions_hl7_json/HL7_definicion/CodeSystem-ICD10CO.json
 */
class Icd10coSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $csvFile = __DIR__ . '/CodeSystem-ICD10CO.csv';

        if (!file_exists($csvFile)) {
            $this->command->error("CSV file not found at: $csvFile");
            return;
        }

        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            $this->command->error("Unable to open CSV file: $csvFile");
            return;
        }

        $this->command->info("Seeding ICD10CO from CSV...");

        $batchSize = 500;
        $data = [];
        $count = 0;

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            // Asumimos que la primera columna es 'code' y la segunda 'display'
            if (count($row) < 2) {
                continue;
            }

            $code = trim($row[0]);
            $display = trim($row[1]);

            // Skip header if present (check if code is 'code' or similar, or just try to process all)
            // Based on previous cat, the first line is data: A000,"COLERA..."
            // So we don't need to skip header based on the file view.

            $data[] = [
                'code'       => $code,
                'display'    => $display,
                'active'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($data) >= $batchSize) {
                DB::table('icd10co')->upsert($data, ['code'], ['display', 'updated_at']);
                $data = [];
                $count += $batchSize;
                $this->command->info("Processed $count records...");
            }
        }

        if (!empty($data)) {
            DB::table('icd10co')->upsert($data, ['code'], ['display', 'updated_at']);
            $count += count($data);
        }

        fclose($handle);
        $this->command->info("Icd10coSeeder completed. Total records: $count");
    }
}
