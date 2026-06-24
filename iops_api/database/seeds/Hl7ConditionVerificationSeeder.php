<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para el catálogo HL7: ConditionVerificationStatus
 *
 * Pobla las tablas hl7_catalogs y hl7_catalog_items con los datos
 * del CodeSystem de Condition Verification Status.
 *
 * Fuente: definitions_hl7_json/HL7_definicion/CodeSystem-condition-ver-status.json
 */
class Hl7ConditionVerificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // =====================================================================
        // MASTER: Catálogo ConditionVerificationStatus
        // =====================================================================
        $catalogData = [
            'resource_type'  => 'CodeSystem',
            'name'           => 'ConditionVerificationStatus',
            'url'            => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
            'version'        => '2.0.1',
            'title'          => 'ConditionVerificationStatus',
            'status'         => 'active',
            'experimental'   => false,
            'publisher'      => 'Health Level Seven International',
            'description'    => 'The verification status to support or decline the clinical status of the condition or diagnosis.',
            'case_sensitive' => true,
            'content'        => 'complete',
        ];

        // Insertar o actualizar el catálogo (idempotente por 'name')
        DB::table('hl7_catalogs')->updateOrInsert(
            ['name' => $catalogData['name']],
            array_merge($catalogData, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        // Obtener el ID del catálogo recién insertado/actualizado
        $catalogId = DB::table('hl7_catalogs')
            ->where('name', 'ConditionVerificationStatus')
            ->value('id');

        // =====================================================================
        // DETALLE: Conceptos del catálogo (concept[])
        // =====================================================================
        // Nota: Los conceptos anidados en el JSON se aplanan para la inserción.
        $concepts = [
            [
                'code'       => 'unconfirmed',
                'display'    => 'Unconfirmed',
                'definition' => 'There is not sufficient evidence to assert the presence of the subject\'s condition.',
            ],
            [
                'code'       => 'provisional',
                'display'    => 'Provisional',
                'definition' => 'This is a tentative diagnosis - still a candidate that is under consideration.',
            ],
            [
                'code'       => 'differential',
                'display'    => 'Differential',
                'definition' => 'One of a set of potential (and typically mutually exclusive) diagnoses asserted to further guide the diagnostic process and preliminary treatment.',
            ],
            [
                'code'       => 'confirmed',
                'display'    => 'Confirmed',
                'definition' => 'There is sufficient evidence to assert the presence of the subject\'s condition.',
            ],
            [
                'code'       => 'refuted',
                'display'    => 'Refuted',
                'definition' => 'This condition has been ruled out by subsequent diagnostic and clinical evidence.',
            ],
            [
                'code'       => 'entered-in-error',
                'display'    => 'Entered in Error',
                'definition' => 'The statement was entered in error and is not valid.',
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
    }
}
