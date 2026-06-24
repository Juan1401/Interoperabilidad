<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seeder para el catálogo HL7: ParentescoAntecedente
 *
 * Pobla las tablas hl7_catalogs y hl7_catalog_items con los datos
 * del CodeSystem de Parentesco del antecedente familiar.
 *
 * Fuente: definitions_hl7_json/HL7_definicion/CodeSystem-ParentescoAntecedente.json
 *
 * Conceptos (4):
 *   01 - Padres, 02 - Hermanos, 03 - Tíos, 04 - Abuelos
 */
class Hl7ParentescoAntecedenteSeeder extends Seeder
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
            // MASTER: Catálogo ParentescoAntecedente
            // =====================================================================
            $catalogData = [
                'resource_type'  => 'CodeSystem',
                'name'           => 'ParentescoAntecedente',
                'language'       => 'es',
                'url'            => 'https://fhir.minsalud.gov.co/rda/CodeSystem/ParentescoAntecedente',
                'version'        => '0.7.1',
                'title'          => 'CodeSystem: Parentesco del antecedente familiar',
                'status'         => 'active',
                'experimental'   => false,
                'date'           => '2022-06-16',
                'publisher'      => 'Ministerio de Salud y Protección Social de Colombia',
                'description'    => 'Código de clasificación del parentesco del antecedente familiar',
                'purpose'        => 'Este sistema de codificación se utiliza como nomenclador de parentesco del antecedente familiar',
                'copyright'      => 'MinSalud Colombia, CC-BY-4.0 2024+',
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

            Log::info('Hl7ParentescoAntecedenteSeeder: Catálogo master insertado/actualizado.');

            // Obtener el ID del catálogo recién insertado/actualizado
            $catalogId = DB::table('hl7_catalogs')
                ->where('name', 'ParentescoAntecedente')
                ->value('id');

            if (!$catalogId) {
                Log::error('Hl7ParentescoAntecedenteSeeder: No se pudo obtener el ID del catálogo.');
                return;
            }

            // =====================================================================
            // DETALLE: Conceptos del catálogo (concept[])
            // Incluyen designation con idioma español
            // =====================================================================
            $concepts = [
                [
                    'code'        => '01',
                    'display'     => 'Padres',
                    'designation' => json_encode([
                        [
                            'language' => 'es',
                            'use'      => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'],
                            'value'    => 'Padres',
                        ]
                    ]),
                ],
                [
                    'code'        => '02',
                    'display'     => 'Hermanos',
                    'designation' => json_encode([
                        [
                            'language' => 'es',
                            'use'      => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'],
                            'value'    => 'Hermanos',
                        ]
                    ]),
                ],
                [
                    'code'        => '03',
                    'display'     => 'Tíos',
                    'designation' => json_encode([
                        [
                            'language' => 'es',
                            'use'      => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'],
                            'value'    => 'Tíos',
                        ]
                    ]),
                ],
                [
                    'code'        => '04',
                    'display'     => 'Abuelos',
                    'designation' => json_encode([
                        [
                            'language' => 'es',
                            'use'      => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'],
                            'value'    => 'Abuelos',
                        ]
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
                        'designation' => $concept['designation'],
                        'active'      => true,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]
                );
            }

            Log::info("Hl7ParentescoAntecedenteSeeder: {$catalogData['count']} conceptos insertados/actualizados para catálogo ID {$catalogId}.");
        } catch (\Exception $e) {
            Log::error('Hl7ParentescoAntecedenteSeeder: Error durante el seeding - ' . $e->getMessage());
            throw $e;
        }
    }
}
