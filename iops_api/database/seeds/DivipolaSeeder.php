<?php

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Municipality;
use Illuminate\Support\Facades\Log;

class DivipolaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $jsonPath = __DIR__ . '/json/CodeSystem-DIVIPOLA.json';

        if (!file_exists($jsonPath)) {
            $msg = "No se encontró el archivo JSON de DIVIPOLA en: {$jsonPath}";
            if ($this->command) {
                $this->command->error($msg);
            }
            Log::error($msg);
            return;
        }

        $jsonContent = file_get_contents($jsonPath);
        $data = json_decode($jsonContent, true);

        if (!isset($data['concept'])) {
            $msg = "El JSON de DIVIPOLA no tiene la estructura esperada (falta 'concept')";
            if ($this->command) {
                $this->command->error($msg);
            }
            Log::error($msg);
            return;
        }

        $totalDepartments = 0;
        $totalMunicipalities = 0;

        foreach ($data['concept'] as $deptConcept) {
            $department = Department::updateOrCreate(
                ['code' => $deptConcept['code']],
                [
                    'display' => $deptConcept['display'],
                    'active'  => true,
                ]
            );
            $totalDepartments++;

            if (isset($deptConcept['concept'])) {
                foreach ($deptConcept['concept'] as $muniConcept) {
                    $code = trim(str_replace("\u00a0", "", $muniConcept['code']));

                    Municipality::updateOrCreate(
                        ['code' => $code],
                        [
                            'department_id' => $department->id,
                            'display'       => $muniConcept['display'],
                            'definition'    => $muniConcept['definition'] ?? null,
                            'active'        => true,
                        ]
                    );
                    $totalMunicipalities++;
                }
            }
        }

        $summary = "DivipolaSeeder completado: {$totalDepartments} departamentos y {$totalMunicipalities} municipios.";
        if ($this->command) {
            $this->command->info($summary);
        }
        Log::info($summary);
    }
}
