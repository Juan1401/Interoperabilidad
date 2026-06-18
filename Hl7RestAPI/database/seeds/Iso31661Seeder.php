<?php

use Illuminate\Database\Seeder;
use App\Models\Iso31661;

class Iso31661Seeder extends Seeder
{
    /**
     * Mapeo de códigos de grupo padre a code_type.
     */
    private const CODE_TYPE_MAP = [
        'ISO316612' => 'alpha2',
        'ISO316613' => 'alpha3',
        'ISO31661N' => 'numeric',
    ];

    /**
     * Run the database seeds.
     *
     * Lee CodeSystem-ISO31661.json y pobla la tabla iso_3166_1
     * usando updateOrCreate para garantizar idempotencia.
     *
     * @return void
     */
    public function run()
    {
        $jsonFile = __DIR__ . '/json/CodeSystem-ISO31661.json';

        if (!file_exists($jsonFile)) {
            $this->command->error("JSON file not found at: $jsonFile");
            return;
        }

        $json = json_decode(file_get_contents($jsonFile), true);

        if (!$json || !isset($json['concept'])) {
            $this->command->error("Invalid JSON structure in: $jsonFile");
            return;
        }

        $this->command->info("Seeding ISO 3166-1 from JSON...");

        $count = 0;

        foreach ($json['concept'] as $group) {
            $groupCode = $group['code'] ?? null;
            $codeType = self::CODE_TYPE_MAP[$groupCode] ?? 'unknown';

            if (!isset($group['concept'])) {
                continue;
            }

            foreach ($group['concept'] as $concept) {
                Iso31661::updateOrCreate(
                    ['code' => $concept['code']],
                    [
                        'display'   => $concept['display'],
                        'code_type' => $codeType,
                        'active'    => true,
                    ]
                );
                $count++;
            }

            $this->command->info("  -> {$group['display']}: procesado ({$codeType})");
        }

        $this->command->info("Iso31661Seeder completado. Total registros procesados: $count");
    }
}
