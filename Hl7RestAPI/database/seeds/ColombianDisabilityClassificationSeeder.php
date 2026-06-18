<?php

use Illuminate\Database\Seeder;
use App\Models\ColombianDisabilityClassification;

class ColombianDisabilityClassificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Lee CodeSystem-ColombianDisabilityClassification.json y pobla la tabla
     * colombian_disability_classification usando updateOrCreate para idempotencia.
     *
     * @return void
     */
    public function run()
    {
        $jsonFile = __DIR__ . '/json/CodeSystem-ColombianDisabilityClassification.json';

        if (!file_exists($jsonFile)) {
            $this->command->error("JSON file not found at: $jsonFile");
            return;
        }

        $json = json_decode(file_get_contents($jsonFile), true);

        if (!$json || !isset($json['concept'])) {
            $this->command->error("Invalid JSON structure in: $jsonFile");
            return;
        }

        $this->command->info("Seeding Colombian Disability Classification from JSON...");

        $count = 0;

        foreach ($json['concept'] as $concept) {
            // Extraer designación en inglés si existe
            $displayEn = null;
            if (isset($concept['designation'])) {
                foreach ($concept['designation'] as $designation) {
                    if ($designation['language'] === 'en') {
                        $displayEn = trim($designation['value']);
                        break;
                    }
                }
            }

            ColombianDisabilityClassification::updateOrCreate(
                ['code' => $concept['code']],
                [
                    'display'    => $concept['display'],
                    'display_en' => $displayEn,
                    'active'     => true,
                ]
            );
            $count++;
        }

        $this->command->info("ColombianDisabilityClassificationSeeder completado. Total registros procesados: $count");
    }
}
