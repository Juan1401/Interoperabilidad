<?php

use Illuminate\Database\Seeder;
use App\Models\Hl7Catalog;
use App\Models\Hl7CatalogItem;
use Illuminate\Support\Facades\Log;

class Hl7CatalogV2_0203Seeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $jsonPath = database_path('seeds/json/CodeSystem-v2-0203.cs.json');

        if (!file_exists($jsonPath)) {
            $this->command->error("JSON file not found at: {$jsonPath}");
            return;
        }

        $json = json_decode(file_get_contents($jsonPath), true);

        if (!$json) {
            $this->command->error("Failed to decode JSON from: {$jsonPath}");
            return;
        }

        $this->command->info("Seeding HL7 Catalog: v2.0203 (Identifier Type)");

        try {
            // 1. Upsert del catálogo maestro
            $catalog = Hl7Catalog::updateOrCreate(
                ['name' => 'v2.0203'],
                [
                    'resource_type'  => $json['resourceType'] ?? 'CodeSystem',
                    'language'       => $json['language'] ?? 'en',
                    'url'            => $json['url'] ?? 'http://terminology.hl7.org/CodeSystem/v2-0203',
                    'version'        => $json['version'] ?? '2.9',
                    'title'          => $json['title'] ?? 'v2 Identifier Type',
                    'status'         => $json['status'] ?? 'active',
                    'experimental'   => filter_var($json['experimental'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'date'           => isset($json['date']) ? date('Y-m-d', strtotime($json['date'])) : now(),
                    'publisher'      => $json['publisher'] ?? 'HL7, Inc',
                    'description'    => $json['description'] ?? 'FHIR Value set/code system definition for HL7 v2 table 0203 ( Identifier Type)',
                    'case_sensitive' => filter_var($json['caseSensitive'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'content'        => $json['content'] ?? 'complete',
                    'count'          => $json['count'] ?? count($json['concept'] ?? []),
                ]
            );

            $this->command->info("Catalog '{$catalog->name}' (ID: {$catalog->id}) processed.");

            // 2. Upsert de los items (conceptos)
            $concepts = $json['concept'] ?? [];
            $processedCount = 0;

            foreach ($concepts as $concept) {
                Hl7CatalogItem::updateOrCreate(
                    [
                        'hl7_catalog_id' => $catalog->id,
                        'code'           => $concept['code'],
                    ],
                    [
                        'display'     => $concept['display'] ?? null,
                        'definition'  => $concept['definition'] ?? null,
                        'designation' => $concept['designation'] ?? null,
                        'active'      => true,
                    ]
                );
                $processedCount++;
            }

            $this->command->info("Seeded {$processedCount} items for catalog '{$catalog->name}'.");
            Log::info("Hl7CatalogV2_0203Seeder: Proceso completado con éxito. Items: {$processedCount}");
        } catch (\Exception $e) {
            $this->command->error("Error seeding HL7 Catalog v2.0203: " . $e->getMessage());
            Log::error("Hl7CatalogV2_0203Seeder Error: " . $e->getMessage(), ['exception' => $e]);
        }
    }
}
