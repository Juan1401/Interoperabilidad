<?php

use Illuminate\Database\Seeder;
use App\Models\ColombianGenderGroup;
use Illuminate\Support\Facades\Log;

class ColombianGenderGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Carga los grupos de género colombianos desde el CodeSystem FHIR:
     * https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianGenderGroup
     *
     * Idempotente: usa updateOrCreate para evitar duplicados en pipelines CI/CD.
     *
     * @return void
     */
    public function run()
    {
        $jsonPath = __DIR__ . '/json/CodeSystem-ColombianGenderGroup.json';

        if (!file_exists($jsonPath)) {
            $msg = "No se encontró el archivo JSON de ColombianGenderGroup en: {$jsonPath}";
            if ($this->command) {
                $this->command->error($msg);
            }
            Log::error($msg);
            return;
        }

        $jsonContent = file_get_contents($jsonPath);
        $data = json_decode($jsonContent, true);

        if (!isset($data['concept'])) {
            $msg = "El JSON de ColombianGenderGroup no tiene la estructura esperada (falta 'concept')";
            if ($this->command) {
                $this->command->error($msg);
            }
            Log::error($msg);
            return;
        }

        $count = 0;
        foreach ($data['concept'] as $concept) {
            ColombianGenderGroup::updateOrCreate(
                ['code' => $concept['code']],
                [
                    'display' => $concept['display'],
                    'active'  => true,
                ]
            );
            $count++;
        }

        $summary = "ColombianGenderGroupSeeder completado. Total registros procesados: $count";
        if ($this->command) {
            $this->command->info($summary);
        }
        Log::info($summary);
    }
}
