<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para el catálogo HL7: ColombianTechModality
 *
 * Pobla las tablas hl7_catalogs y hl7_catalog_items con los datos
 * del CodeSystem de Modalidad de realización de la tecnología de salud.
 *
 * Fuente: definitions_hl7_json/HL7_definicion/CodeSystem-ColombianTechModality.json
 */
class Hl7ColombianTechModalitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // =====================================================================
        // MASTER: Catálogo ColombianTechModality
        // =====================================================================
        $catalogData = [
            'resource_type'  => 'CodeSystem',
            'name'           => 'ColombianTechModality',
            'language'       => 'es',
            'url'            => 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianTechModality',
            'version'        => '0.7.1',
            'title'          => 'CodeSystem: Modalidad de realización de la tecnología de salud',
            'status'         => 'active',
            'experimental'   => false,
            'date'           => '2023-07-30',
            'publisher'      => 'Ministerio de Salud y Protección Social de Colombia',
            'description'    => 'Sistema de códificación de modalidad de realización de la tecnología de salud.',
            'purpose'        => 'Este sistema de codificación se utiliza como nomenclador de la modalidad de realización de la tecnología de salud.]',
            'copyright'      => 'SISPRO, CCBY4.0 2006+',
            'case_sensitive' => true,
            'content'        => 'complete',
            'count'          => 9,
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
            ->where('name', 'ColombianTechModality')
            ->value('id');

        // =====================================================================
        // DETALLE: Conceptos del catálogo (concept[])
        // =====================================================================
        $concepts = [
            [
                'code'        => '01',
                'display'     => 'Intramural',
                'designation' => json_encode([
                    ['language' => 'es', 'use' => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'], 'value' => 'Intramural'],
                ]),
            ],
            [
                'code'        => '02',
                'display'     => 'Extramural unidad móvil',
                'designation' => json_encode([
                    ['language' => 'es', 'use' => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'], 'value' => 'Extramural unidad móvil'],
                ]),
            ],
            [
                'code'        => '03',
                'display'     => 'Extramural domiciliaria',
                'designation' => json_encode([
                    ['language' => 'es', 'use' => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'], 'value' => 'Extramural domiciliaria'],
                ]),
            ],
            [
                'code'        => '04',
                'display'     => 'Extramural jornada de salud',
                'designation' => json_encode([
                    ['language' => 'es', 'use' => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'], 'value' => 'Extramural jornada de salud'],
                ]),
            ],
            [
                'code'        => '05',
                'display'     => 'Extramural (atención pre hospitalaria o transporte asistencial)',
                'designation' => json_encode([
                    ['language' => 'es', 'use' => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'], 'value' => 'Extramural (atención pre hospitalaria o transporte asistencial)'],
                ]),
            ],
            [
                'code'        => '06',
                'display'     => 'Telemedicina interactiva',
                'designation' => json_encode([
                    ['language' => 'es', 'use' => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'], 'value' => 'Telemedicina interactiva'],
                ]),
            ],
            [
                'code'        => '07',
                'display'     => 'Telemedicina no interactiva',
                'designation' => json_encode([
                    ['language' => 'es', 'use' => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'], 'value' => 'Telemedicina no interactiva'],
                ]),
            ],
            [
                'code'        => '08',
                'display'     => 'Telemedicina - Telexperticia',
                'designation' => json_encode([
                    ['language' => 'es', 'use' => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'], 'value' => 'Telemedicina - Telexperticia'],
                ]),
            ],
            [
                'code'        => '09',
                'display'     => 'Telemedicina - Telemonitoreo',
                'designation' => json_encode([
                    ['language' => 'es', 'use' => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'], 'value' => 'Telemedicina - Telemonitoreo'],
                ]),
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
                    'definition'  => null,
                    'designation' => $concept['designation'],
                    'active'      => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );
        }
    }
}
