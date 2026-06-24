<?php

use Illuminate\Database\Seeder;
use App\Models\ColombianOrganizationIdentifier;
use Illuminate\Support\Facades\Log;

class ColombianOrganizationIdentifierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $jsonPath = __DIR__ . '/json/CodeSystem-ColombianOrganizationIdentifiers.json';

        if (!file_exists($jsonPath)) {
            $msg = "No se encontró el archivo JSON de ColombianOrganizationIdentifiers en: {$jsonPath}";
            if ($this->command) {
                $this->command->error($msg);
            }
            Log::error($msg);
            return;
        }

        $jsonContent = file_get_contents($jsonPath);
        $data = json_decode($jsonContent, true);

        if (!isset($data['concept'])) {
            $msg = "El JSON de ColombianOrganizationIdentifiers no tiene la estructura esperada (falta 'concept')";
            if ($this->command) {
                $this->command->error($msg);
            }
            Log::error($msg);
            return;
        }

        $count = 0;
        foreach ($data['concept'] as $concept) {
            ColombianOrganizationIdentifier::updateOrCreate(
                ['code' => $concept['code']],
                [
                    'display' => $concept['display'],
                    'active'  => true,
                ]
            );
            $count++;
        }

        $summary = "ColombianOrganizationIdentifierSeeder completado. Total registros procesados: $count";
        if ($this->command) {
            $this->command->info($summary);
        }
        Log::info($summary);
    }
}
