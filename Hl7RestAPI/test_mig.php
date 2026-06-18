<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

try {
    // Try direct SQL instead
    DB::statement('ALTER TABLE public.pacientes ADD COLUMN IF NOT EXISTS codigo_etnia VARCHAR(3) NULL');
    DB::statement('ALTER TABLE public.pacientes ADD COLUMN IF NOT EXISTS codigo_discapacidad VARCHAR(3) NULL');
    DB::statement('ALTER TABLE public.pacientes ADD COLUMN IF NOT EXISTS municipio_residencia_divipola_id BIGINT NULL');
    DB::statement('ALTER TABLE public.pacientes ADD COLUMN IF NOT EXISTS zona_residencia VARCHAR(2) NULL');
    
    // Add index if it doesn't exist
    $indexExists = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'pacientes' AND schemaname = 'public' AND indexname = 'idx_pacientes_divipola'");
    if (empty($indexExists)) {
        DB::statement('CREATE INDEX idx_pacientes_divipola ON public.pacientes(municipio_residencia_divipola_id)');
    }
    
    echo "EXITO: Columnas y índice creados correctamente";
} catch (\Exception $e) {
    echo "ERROR SQL: " . $e->getMessage();
}
