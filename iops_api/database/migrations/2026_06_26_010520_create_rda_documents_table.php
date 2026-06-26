<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRdaDocumentsTable extends Migration
{
    public function up()
    {
        Schema::create('rda_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relación con el usuario (médico) que creó el documento
            $table->foreignId('user_id')->nullable()->constrained('users');
            
            // Metadatos
            $table->string('document_type', 50)->comment('Ej: paciente, consulta_externa');
            $table->string('status', 30)->default('DRAFT')->comment('DRAFT, PENDING, SENT, ACCEPTED, REJECTED');
            
            // Las columnas mágicas JSONB
            $table->jsonb('form_payload')->nullable()->comment('El JSON tal cual llega de Angular');
            $table->jsonb('fhir_bundle_generated')->nullable()->comment('El XML/JSON de FHIR final');
            $table->jsonb('minsalud_response')->nullable()->comment('Respuesta técnica del Ministerio');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('rda_documents');
    }
}