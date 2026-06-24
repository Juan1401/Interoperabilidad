<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seeder para el catálogo HL7: MedicationStatementStatus
 *
 * Pobla las tablas hl7_catalogs y hl7_catalog_items con los datos
 * del CodeSystem de Medication Statement Status Codes.
 *
 * Fuente: definitions_hl7_json/HL7_definicion/codesystem-medication-statement-status.json
 *
 * Conceptos (8):
 *   active, completed, entered-in-error, intended,
 *   stopped, on-hold, unknown, not-taken
 */
class Hl7MedicationStatementStatusSeeder extends Seeder
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
            // MASTER: Catálogo MedicationStatementStatus
            // =====================================================================
            $catalogData = [
                'resource_type'  => 'CodeSystem',
                'name'           => 'MedicationStatementStatusCodes',
                'url'            => 'http://hl7.org/fhir/CodeSystem/medication-statement-status',
                'version'        => '4.0.1',
                'title'          => 'Medication status codes',
                'status'         => 'draft',
                'experimental'   => false,
                'publisher'      => 'FHIR Project team',
                'description'    => 'Medication Status Codes',
                'case_sensitive' => true,
                'content'        => 'complete',
                'count'          => 8,
            ];

            // Insertar o actualizar el catálogo (idempotente por 'name')
            DB::table('hl7_catalogs')->updateOrInsert(
                ['name' => $catalogData['name']],
                array_merge($catalogData, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );

            Log::info('Hl7MedicationStatementStatusSeeder: Catálogo master insertado/actualizado.');

            // Obtener el ID del catálogo recién insertado/actualizado
            $catalogId = DB::table('hl7_catalogs')
                ->where('name', 'MedicationStatementStatusCodes')
                ->value('id');

            if (!$catalogId) {
                Log::error('Hl7MedicationStatementStatusSeeder: No se pudo obtener el ID del catálogo.');
                return;
            }

            // =====================================================================
            // DETALLE: Conceptos del catálogo (concept[])
            // =====================================================================
            $concepts = [
                [
                    'code'       => 'active',
                    'display'    => 'Active',
                    'definition' => 'The medication is still being taken.',
                ],
                [
                    'code'       => 'completed',
                    'display'    => 'Completed',
                    'definition' => 'The medication is no longer being taken.',
                ],
                [
                    'code'       => 'entered-in-error',
                    'display'    => 'Entered in Error',
                    'definition' => 'Some of the actions that are implied by the medication statement may have occurred.  For example, the patient may have taken some of the medication.  Clinical decision support systems should take this status into account.',
                ],
                [
                    'code'       => 'intended',
                    'display'    => 'Intended',
                    'definition' => 'The medication may be taken at some time in the future.',
                ],
                [
                    'code'       => 'stopped',
                    'display'    => 'Stopped',
                    'definition' => 'Actions implied by the statement have been permanently halted, before all of them occurred. This should not be used if the statement was entered in error.',
                ],
                [
                    'code'       => 'on-hold',
                    'display'    => 'On Hold',
                    'definition' => 'Actions implied by the statement have been temporarily halted, but are expected to continue later. May also be called \'suspended\'.',
                ],
                [
                    'code'       => 'unknown',
                    'display'    => 'Unknown',
                    'definition' => 'The state of the medication use is not currently known.',
                ],
                [
                    'code'       => 'not-taken',
                    'display'    => 'Not Taken',
                    'definition' => 'The medication was not consumed by the patient',
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

            Log::info("Hl7MedicationStatementStatusSeeder: {$catalogData['count']} conceptos insertados/actualizados para catálogo ID {$catalogId}.");
        } catch (\Exception $e) {
            Log::error('Hl7MedicationStatementStatusSeeder: Error durante el seeding - ' . $e->getMessage());
            throw $e;
        }
    }
}
