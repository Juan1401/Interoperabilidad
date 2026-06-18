<?php

use Illuminate\Database\Seeder;
use App\Models\ColombianPersonIdentifier;

class ColombianPersonIdentifierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Lee CodeSystem-ColombianPersonIdentifier.json y pobla la tabla
     * colombian_person_identifier usando updateOrCreate para idempotencia.
     * Procesa la estructura jerárquica de forma plana para la tabla.
     *
     * @return void
     */
    public function run()
    {
        $jsonFile = __DIR__ . '/json/CodeSystem-ColombianPersonIdentifier.json';

        if (!file_exists($jsonFile)) {
            if ($this->command) {
                $this->command->error("JSON file not found at: $jsonFile");
            }
            return;
        }

        $json = json_decode(file_get_contents($jsonFile), true);

        if (!$json || !isset($json['concept'])) {
            if ($this->command) {
                $this->command->error("Invalid JSON structure in: $jsonFile");
            }
            return;
        }

        if ($this->command) {
            $this->command->info("Seeding Colombian Person Identifiers from JSON...");
        }

        $count = $this->processConcepts($json['concept']);

        if ($this->command) {
            $this->command->info("ColombianPersonIdentifierSeeder completado. Total registros procesados: $count");
        }
    }

    /**
     * Procesa recursivamente la jerarquía de conceptos y los guarda en la DB.
     *
     * @param array $concepts
     * @return int
     */
    private function processConcepts(array $concepts): int
    {
        $count = 0;

        foreach ($concepts as $concept) {
            ColombianPersonIdentifier::updateOrCreate(
                ['code' => $concept['code']],
                [
                    'display'    => $concept['display'],
                    'definition' => $concept['definition'] ?? null,
                    'active'     => true,
                ]
            );
            $count++;

            // Procesar hijos si existen
            if (isset($concept['concept'])) {
                $count += $this->processConcepts($concept['concept']);
            }
        }

        return $count;
    }
}
