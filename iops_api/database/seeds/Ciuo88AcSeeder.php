<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Ciuo88AcSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $jsonPath = base_path('database/seeds/json/CodeSystem-CIUO88AC.json');

        if (!file_exists($jsonPath)) {
            Log::error("El archivo JSON CIUO-88 A.C. no fue encontrado en: {$jsonPath}");
            if ($this->command) {
                $this->command->error("El archivo JSON CIUO-88 A.C. no fue encontrado en: {$jsonPath}");
            } else {
                echo "El archivo JSON CIUO-88 A.C. no fue encontrado en: {$jsonPath}\n";
            }
            return;
        }

        $jsonContent = file_get_contents($jsonPath);
        $data = json_decode($jsonContent, true);

        if (!isset($data['concept']) || !is_array($data['concept'])) {
            if ($this->command) {
                $this->command->error("El archivo JSON no tiene el formato esperado (falta el nodo principal 'concept').");
            } else {
                echo "El archivo JSON no tiene el formato esperado (falta el nodo principal 'concept').\n";
            }
            return;
        }

        if ($this->command) {
            $this->command->info('Procesando conceptos de CIUO-88 A.C...');
        } else {
            echo "Procesando conceptos de CIUO-88 A.C...\n";
        }

        DB::beginTransaction();
        try {
            $this->processConcepts($data['concept'], null, 1);
            DB::commit();
            if ($this->command) {
                $this->command->info('Siembra de CIUO-88 A.C. completada exitosamente.');
            } else {
                echo "Siembra de CIUO-88 A.C. completada exitosamente.\n";
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fallo al sembrar la tabla ciuo_88_ac: ' . $e->getMessage());
            if ($this->command) {
                $this->command->error('Error durante la siembra. Cambios revertidos. Revisa el log para más detalles.');
            } else {
                echo "Error durante la siembra. Cambios revertidos.\n";
            }
        }
    }

    /**
     * Procesa recursivamente cada nivel del JSON para almacenarlos en base de datos.
     *
     * @param array $concepts
     * @param string|null $parentCode
     * @param int $level
     * @return void
     */
    private function processConcepts(array $concepts, ?string $parentCode = null, int $level = 1): void
    {
        foreach ($concepts as $concept) {
            $code = $concept['code'] ?? null;
            $display = $concept['display'] ?? 'Sin descripción';
            
            if (!$code) {
                continue;
            }

            // Usar updateOrCreate para asegurar idempotencia (no arroja duplicados si ya existen)
            DB::table('ihce.ciuo_88_ac')->updateOrInsert(
                // Atributos para conciliar si existe
                ['code' => $code],
                // Atributos para actualizar/insertar
                [
                    'display' => $display,
                    'parent_code' => $parentCode,
                    'level' => $level,
                    'updated_at' => now(),
                    // Solo en caso de insert, Laravel no auto-inserta created_at si usamos updateOrInsert del QueryBuilder
                    // Para que no de error si require insertOrUpdate en QueryBuilder es mejor dejar created_at si es nuevo. Sin embargo
                    // Query builder updateOrInsert no tiene callbacks nativos fáciles, lo solventamos con una condición:
                ]
            );
            
            // Fix temporal para created_at con QueryBuilder updateOrInsert
            $exists = DB::table('ihce.ciuo_88_ac')->where('code', $code)->whereNull('created_at')->first();
            if ($exists) {
                 DB::table('ihce.ciuo_88_ac')->where('code', $code)->update(['created_at' => now()]);
            }

            // Verificar si hay ramas dependientes (recursividad)
            if (isset($concept['concept']) && is_array($concept['concept'])) {
                $this->processConcepts($concept['concept'], $code, $level + 1);
            }
        }
    }
}
