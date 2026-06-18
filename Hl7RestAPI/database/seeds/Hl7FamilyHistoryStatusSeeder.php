<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seeder para el catálogo HL7: FamilyHistoryStatus
 *
 * Pobla las tablas hl7_catalogs y hl7_catalog_items con los datos
 * del CodeSystem de Family History Status.
 *
 * Fuente: definitions_hl7_json/HL7_definicion/codesystem-history-status.json
 *
 * Conceptos (4):
 *   partial, completed, entered-in-error, health-unknown
 */
class Hl7FamilyHistoryStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        try {
            // =====================================================================
            // MASTER: Catálogo FamilyHistoryStatus
            // =====================================================================
            $catalogData = [
                'resource_type'  => 'CodeSystem',
                'name'           => 'FamilyHistoryStatus',
                'url'            => 'http://hl7.org/fhir/history-status',
                'version'        => '4.0.1',
                'title'          => 'FamilyHistoryStatus',
                'status'         => 'draft',
                'experimental'   => false,
                'date'           => '2019-11-01',
                'publisher'      => 'HL7 (FHIR Project)',
                'description'    => 'A code that identifies the status of the family history record.',
                'case_sensitive' => true,
                'content'        => 'complete',
                'count'          => 4,
            ];

            // Insertar o actualizar el catálogo (idempotente por 'name')
            DB::table('hl7_catalogs')->updateOrInsert(
                ['name' => $catalogData['name']],
                array_merge($catalogData, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );

            Log::info('Hl7FamilyHistoryStatusSeeder: Catálogo master insertado/actualizado.');

            // Obtener el ID del catálogo recién insertado/actualizado
            $catalogId = DB::table('hl7_catalogs')
                ->where('name', 'FamilyHistoryStatus')
                ->value('id');

            if (!$catalogId) {
                Log::error('Hl7FamilyHistoryStatusSeeder: No se pudo obtener el ID del catálogo.');
                return;
            }

            // =====================================================================
            // DETALLE: Conceptos del catálogo (concept[])
            // =====================================================================
            $concepts = [
                [
                    'code'       => 'partial',
                    'display'    => 'Partial',
                    'definition' => 'Some health information is known and captured, but not complete - see notes for details.',
                ],
                [
                    'code'       => 'completed',
                    'display'    => 'Completed',
                    'definition' => 'All available related health information is captured as of the date (and possibly time) when the family member history was taken.',
                ],
                [
                    'code'       => 'entered-in-error',
                    'display'    => 'Entered in Error',
                    'definition' => 'This instance should not have been part of this patient\'s medical record.',
                ],
                [
                    'code'       => 'health-unknown',
                    'display'    => 'Health Unknown',
                    'definition' => 'Health information for this family member is unavailable/unknown.',
                ],
            ];

            foreach ($concepts as $concept) {
                DB::table('hl7_catalog_items')->updateOrInsert(
                    [
                        'hl7_catalog_id' => $catalogId,
                        'code'           => $concept['code'],
                    ],
                    [
                        'display'     => $concept['display'],
                        'definition'  => $concept['definition'],
                        'active'      => true,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]
                );
            }

            Log::info("Hl7FamilyHistoryStatusSeeder: {$catalogData['count']} conceptos insertados/actualizados para catálogo ID {$catalogId}.");
        } catch (\Exception $e) {
            Log::error('Hl7FamilyHistoryStatusSeeder: Error durante el seeding - ' . $e->getMessage());
            throw $e;
        }
    }
}
