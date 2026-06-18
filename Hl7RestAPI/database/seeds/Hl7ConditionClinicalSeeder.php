<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para el catálogo HL7: ConditionClinical
 *
 * Pobla las tablas hl7_catalogs y hl7_catalog_items con los datos
 * del CodeSystem de Condition Clinical Status.
 *
 * Fuente: definitions_hl7_json/HL7_definicion/Codesystem-Condition-Clinical.json
 */
class Hl7ConditionClinicalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // =====================================================================
        // MASTER: Catálogo ConditionClinical
        // =====================================================================
        $catalogData = [
            'resource_type'  => 'CodeSystem',
            'name'           => 'ConditionClinicalStatusCodes',
            'url'            => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
            'version'        => '4.0.1',
            'title'          => 'Condition Clinical Status Codes',
            'status'         => 'draft',
            'experimental'   => false,
            'publisher'      => 'FHIR Project team',
            'description'    => 'Preferred value set for Condition Clinical Status.',
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
            ->where('name', 'ConditionClinicalStatusCodes')
            ->value('id');

        // =====================================================================
        // DETALLE: Conceptos del catálogo (concept[])
        // =====================================================================
        // Nota: Los conceptos anidados en el JSON se aplanan para la inserción.
        $concepts = [
            [
                'code'       => 'active',
                'display'    => 'Active',
                'definition' => 'The subject is currently experiencing the symptoms of the condition or there is evidence of the condition.',
            ],
            [
                'code'       => 'recurrence',
                'display'    => 'Recurrence',
                'definition' => 'The subject is experiencing a re-occurence or repeating of a previously resolved condition, e.g. urinary tract infection, pancreatitis, cholangitis, conjunctivitis.',
            ],
            [
                'code'       => 'relapse',
                'display'    => 'Relapse',
                'definition' => 'The subject is experiencing a return of a condition, or signs and symptoms after a period of improvement or remission, e.g. relapse of cancer, multiple sclerosis, rheumatoid arthritis, systemic lupus erythematosus, bipolar disorder, [psychotic relapse of] schizophrenia, etc.',
            ],
            [
                'code'       => 'inactive',
                'display'    => 'Inactive',
                'definition' => 'The subject is no longer experiencing the symptoms of the condition or there is no longer evidence of the condition.',
            ],
            [
                'code'       => 'remission',
                'display'    => 'Remission',
                'definition' => 'The subject is no longer experiencing the symptoms of the condition, but there is a risk of the symptoms returning.',
            ],
            [
                'code'       => 'resolved',
                'display'    => 'Resolved',
                'definition' => 'The subject is no longer experiencing the symptoms of the condition and there is a negligible perceived risk of the symptoms returning.',
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
