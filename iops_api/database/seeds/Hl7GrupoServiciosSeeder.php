<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para el catálogo HL7: GrupoServicios
 *
 * Pobla las tablas hl7_catalogs y hl7_catalog_items con los datos
 * del CodeSystem de Grupo de Servicios.
 *
 * Fuente: definitions_hl7_json/HL7_definicion/CodeSystem-GrupoServicios.json
 */
class Hl7GrupoServiciosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // =====================================================================
        // MASTER: Catálogo GrupoServicios
        // =====================================================================
        $catalogData = [
            'resource_type'  => 'CodeSystem',
            'name'           => 'GrupoServicios',
            'language'       => 'es',
            'url'            => 'https://fhir.minsalud.gov.co/rda/CodeSystem/GrupoServicios',
            'version'        => '0.7.1',
            'title'          => 'CodeSystem: Grupo de Servicios',
            'status'         => 'active',
            'experimental'   => false,
            'date'           => '2022-06-16',
            'publisher'      => 'Ministerio de Salud y Protección Social de Colombia',
            'description'    => 'Sistema de códificación de grupo de servicio o ámbito de prescripción o administración de una tecnología en salud.]',
            'purpose'        => 'Este sistema de codificación se utiliza como nomenclador de grupo de servicio o ámbito de prescripción o administración de una tecnología en salud.]',
            'copyright'      => 'SISPRO, CCBY4.0 2022+',
            'case_sensitive' => true,
            'content'        => 'complete',
            'count'          => 5,
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
            ->where('name', 'GrupoServicios')
            ->value('id');

        // =====================================================================
        // DETALLE: Conceptos del catálogo (concept[])
        // =====================================================================
        $concepts = [
            [
                'code'        => '01',
                'display'     => 'Consulta externa',
                'designation' => json_encode([
                    ['language' => 'es', 'use' => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'], 'value' => 'Consulta externa'],
                ]),
            ],
            [
                'code'        => '02',
                'display'     => 'Apoyo diagnóstico y complementación terapéutica',
                'designation' => json_encode([
                    ['language' => 'es', 'use' => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'], 'value' => 'Apoyo diagnóstico y complementación terapéutica'],
                ]),
            ],
            [
                'code'        => '03',
                'display'     => 'Internación',
                'designation' => json_encode([
                    ['language' => 'es', 'use' => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'], 'value' => 'Internación'],
                ]),
            ],
            [
                'code'        => '04',
                'display'     => 'Quirúrgico',
                'designation' => json_encode([
                    ['language' => 'es', 'use' => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'], 'value' => 'Quirúrgico'],
                ]),
            ],
            [
                'code'        => '05',
                'display'     => 'Atención inmediata',
                'designation' => json_encode([
                    ['language' => 'es', 'use' => ['system' => 'http://terminology.hl7.org/CodeSystem/designation-usage', 'code' => 'display'], 'value' => 'Atención inmediata'],
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
