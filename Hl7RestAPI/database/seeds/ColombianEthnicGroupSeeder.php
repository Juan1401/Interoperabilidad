<?php

use Illuminate\Database\Seeder;
use App\Models\ColombianEthnicGroup;

class ColombianEthnicGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Lee CodeSystem-ColombianEthnicGroup.json y pobla la tabla colombian_ethnic_group
     * usando updateOrCreate para garantizar idempotencia.
     *
     * @return void
     */
    public function run()
    {
        $jsonFile = __DIR__ . '/json/CodeSystem-ColombianEthnicGroup.json';

        if (!file_exists($jsonFile)) {
            $this->command->error("JSON file not found at: $jsonFile");
            return;
        }

        $json = json_decode(file_get_contents($jsonFile), true);

        if (!$json || !isset($json['concept'])) {
            $this->command->error("Invalid JSON structure in: $jsonFile");
            return;
        }

        $this->command->info("Seeding Colombian Ethnic Group from JSON...");

        $count = 0;

        foreach ($json['concept'] as $concept) {
            ColombianEthnicGroup::updateOrCreate(
                ['code' => $concept['code']],
                [
                    'display' => $concept['display'],
                    'active'  => true,
                ]
            );
            $count++;
        }

        $this->command->info("ColombianEthnicGroupSeeder completado. Total registros procesados: $count");
    }
}
