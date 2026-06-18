<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nombre de la tabla objetivo (con esquema PostgreSQL explícito).
     */
    private string $table = 'public.pacientes';

    /**
     * Run the migrations.
     * Agrega los campos demográficos requeridos por Minsalud FHIR para RDA.
     *
     * @return void
     */
    public function up()
    {
        // Usamos SQL directo para evitar conflictos del Schema Builder con esquemas de PG.
        // ADD COLUMN IF NOT EXISTS garantiza idempotencia (no falla si la columna ya existe).
        DB::statement("ALTER TABLE {$this->table} ADD COLUMN IF NOT EXISTS codigo_etnia VARCHAR(3) NULL");
        DB::statement("ALTER TABLE {$this->table} ADD COLUMN IF NOT EXISTS codigo_discapacidad VARCHAR(3) NULL");
        DB::statement("ALTER TABLE {$this->table} ADD COLUMN IF NOT EXISTS municipio_residencia_divipola_id BIGINT NULL");
        DB::statement("ALTER TABLE {$this->table} ADD COLUMN IF NOT EXISTS zona_residencia VARCHAR(2) NULL");

        // Índice referencial (no FK estricta para no depender del schema de municipalities)
        DB::statement("
            DO \$\$ BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_indexes
                    WHERE schemaname = 'public'
                    AND tablename = 'pacientes'
                    AND indexname = 'idx_pacientes_divipola'
                ) THEN
                    CREATE INDEX idx_pacientes_divipola ON {$this->table}(municipio_residencia_divipola_id);
                END IF;
            END \$\$;
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP INDEX IF EXISTS public.idx_pacientes_divipola");
        DB::statement("ALTER TABLE {$this->table} DROP COLUMN IF EXISTS zona_residencia");
        DB::statement("ALTER TABLE {$this->table} DROP COLUMN IF EXISTS municipio_residencia_divipola_id");
        DB::statement("ALTER TABLE {$this->table} DROP COLUMN IF EXISTS codigo_discapacidad");
        DB::statement("ALTER TABLE {$this->table} DROP COLUMN IF EXISTS codigo_etnia");
    }
};
