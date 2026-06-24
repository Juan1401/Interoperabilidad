<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Hl7Catalog;
use App\Models\Hl7CatalogItem;

class Hl7AllergyIntoleranceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->seedFromJson('CodeSystem-TipoAlergia.json');
        $this->seedFromJson('codesystem-allergyintolerance-verification.json');
        $this->seedFromJson('codesystem-allergyintolerance-clinical.json');
    }

    private function seedFromJson($filename)
    {
        $path = __DIR__ . '/json/' . $filename;

        if (!file_exists($path)) {
            $this->command->error("JSON file not found: $path");
            return;
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (!$data) {
            $this->command->error("Error decoding JSON: $filename");
            return;
        }

        $this->command->info("Seeding {$data['name']}...");

        // Create or Update Catalog
        $catalog = Hl7Catalog::updateOrCreate(
            ['name' => $data['name']],
            [
                'resource_type' => $data['resourceType'] ?? 'CodeSystem',
                'language'      => $data['language'] ?? 'es',
                'url'           => $data['url'] ?? null,
                'version'       => $data['version'] ?? null,
                'title'         => $data['title'] ?? null,
                'status'        => $data['status'] ?? 'active',
                'experimental'  => $data['experimental'] ?? false,
                'date'          => isset($data['date']) ? date('Y-m-d', strtotime($data['date'])) : null,
                'publisher'     => $data['publisher'] ?? null,
                'description'   => $data['description'] ?? null,
                'purpose'       => $data['purpose'] ?? null,
                'copyright'     => $data['copyright'] ?? null,
                'case_sensitive' => $data['caseSensitive'] ?? false,
                'content'       => $data['content'] ?? null,
                'count'         => $data['count'] ?? count($data['concept'] ?? []),
            ]
        );

        // Process Concepts
        if (isset($data['concept']) && is_array($data['concept'])) {
            foreach ($data['concept'] as $concept) {
                // Flatten concepts if necessary (for clinical status which has nested concepts)
                $this->processConcept($catalog, $concept);
            }
        }
    }

    private function processConcept($catalog, $concept)
    {
        Hl7CatalogItem::updateOrCreate(
            [
                'hl7_catalog_id' => $catalog->id,
                'code'           => $concept['code']
            ],
            [
                'display'     => $concept['display'],
                'definition'  => $concept['definition'] ?? null,
                'designation' => $concept['designation'] ?? null,
                'active'      => true
            ]
        );

        // Recursively process nested concepts
        if (isset($concept['concept']) && is_array($concept['concept'])) {
            foreach ($concept['concept'] as $childConcept) {
                $this->processConcept($catalog, $childConcept);
            }
        }
    }
}
