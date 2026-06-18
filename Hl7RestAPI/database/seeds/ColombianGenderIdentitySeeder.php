<?php

use Illuminate\Database\Seeder;
use App\Models\ColombianGenderIdentity;

class ColombianGenderIdentitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Lee CodeSystem-ColombianGenderIdentity.json y pobla la tabla
     * colombian_gender_identity usando updateOrCreate para idempotencia.
     *
     * @return void
     */
    public function run()
    {
        $jsonFile = __DIR__ . '/json/CodeSystem-ColombianGenderIdentity.json';

        if (!file_exists($jsonFile)) {
            $this->command->error("JSON file not found at: $jsonFile");
            return;
        }

        $json = json_decode(file_get_contents($jsonFile), true);

        if (!$json || !isset($json['concept'])) {
            $this->command->error("Invalid JSON structure in: $jsonFile");
            return;
        }

        $this->command->info("Seeding Colombian Gender Identity from JSON...");

        $count = 0;

        foreach ($json['concept'] as $concept) {
            ColombianGenderIdentity::updateOrCreate(
                ['code' => $concept['code']],
                [
                    'display' => $concept['display'],
                    'active'  => true,
                ]
            );
            $count++;
        }

        $this->command->info("ColombianGenderIdentitySeeder completado. Total registros procesados: $count");
    }
}
