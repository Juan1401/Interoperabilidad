<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateAuditoriaAccesosLinksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Crear el esquema si no existe
        DB::statement('CREATE SCHEMA IF NOT EXISTS ihce;');

        // 2. Crear la tabla en el schema especificado con el comando raw (ya que pide extensiones y tipos nativos especificos como UUID e INET de postgres)
        DB::statement("
            CREATE TABLE ihce.auditoria_accesos_links (
                uuid UUID PRIMARY KEY,                   
                usuario_id VARCHAR(50) NOT NULL,        
                ip INET NOT NULL,                       
                estado VARCHAR(20) DEFAULT 'Usado', 
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 3. Crear indices
        DB::statement('CREATE INDEX idx_auditoria_usuario ON ihce.auditoria_accesos_links(usuario_id);');
        DB::statement('CREATE INDEX idx_auditoria_estado ON ihce.auditoria_accesos_links(estado);');

        // 4. Agregar el comentario a la tabla
        DB::statement(
            "COMMENT ON TABLE ihce.auditoria_accesos_links IS 'Registra el acceso y uso de links encriptados para el env\u00edo IHCE';"
        );
        DB::statement(
            "COMMENT ON COLUMN ihce.auditoria_accesos_links.estado IS 'Pendiente, Usado, Expirado';"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS ihce.auditoria_accesos_links;');
    }
}
