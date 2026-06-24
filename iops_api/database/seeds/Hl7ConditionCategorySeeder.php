<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para el catálogo HL7: ConditionCategory
 *
 * Pobla las tablas hl7_catalogs y hl7_catalog_items con los datos
 * del CodeSystem de Condition Category Codes.
 *
 * Fuente: definitions_hl7_json/HL7_definicion/CodeSystem-condition-category.json
 */
class Hl7ConditionCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // =====================================================================
        // MASTER: Catálogo ConditionCategoryCodes
        // =====================================================================
        $catalogData = [
            'resource_type'  => 'CodeSystem',
            'name'           => 'ConditionCategoryCodes',
            'url'            => 'http://terminology.hl7.org/CodeSystem/condition-category',
            'version'        => '2.0.0',
            'title'          => 'Condition Category Codes',
            'status'         => 'active',
            'experimental'   => false,
            'publisher'      => 'Health Level Seven International',
            'description'    => 'Preferred value set for Condition Categories.',
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
            ->where('name', 'ConditionCategoryCodes')
            ->value('id');

        // =====================================================================
        // DETALLE: Conceptos del catálogo (concept[])
        // =====================================================================
        $concepts = [
            [
                'code'       => 'problem-list-item',
                'display'    => 'Problem List Item',
                'definition' => 'An item on a problem list that can be managed over time and can be expressed by a practitioner (e.g. physician, nurse), patient, or related person.',
            ],
            [
                'code'       => 'encounter-diagnosis',
                'display'    => 'Encounter Diagnosis',
                'definition' => 'A point in time diagnosis (e.g. from a physician or nurse) in context of an encounter.',
            ],
            [
                'code'       => 'diagnostic-report-impression',
                'display'    => 'Diagnostic Report Impression',
                'definition' => 'A diagnosis or differential diagnosis item that is expressed in a diagnostic report.',
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
