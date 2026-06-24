<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ihce.rda_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->index();
            $table->string('patient_document_type', 3);
            $table->string('patient_document_number', 32);
            $table->integer('tipo_rda_id')->unsigned()->index();
            $table->string('rda_id');
            $table->timestamps();

            // Índice compuesto para búsquedas por paciente
            // NOTA: No se define FK hacia public.pacientes porque este log registra
            // pacientes del Ministerio (IHCE) que pueden no existir en la BD local.
            $table->index(['patient_document_type', 'patient_document_number'], 'idx_patient_document');

            $table->foreign('tipo_rda_id', 'fk_rda_audit_tipo_rda')
                  ->references('id')
                  ->on('ihce.ihce_cat_tipos_rda')
                  ->onUpdate('cascade')
                  ->onDelete('restrict');

            // $table->foreign('user_id', 'fk_rda_audit_system_usuario')
            //       ->references('usuario_id')
            //       ->on('public.system_usuarios')
            //       ->onUpdate('cascade')
            //       ->onDelete('restrict');

            // Índice para filtrado por fecha
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ihce.rda_audit_logs');
    }
};
