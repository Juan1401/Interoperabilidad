<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\Hl7\RdaController;
use App\Http\Controllers\Api\SystemVariablesController;
use App\Http\Controllers\Api\Ihce\AuditoriaAccesoLinkController;
use App\Http\Controllers\Api\Hl7\ListaIngresosController;
use App\Http\Controllers\Api\Ihce\ConsultaMinisterioController;
use App\Http\Controllers\Api\Ihce\RdaAuditController;
use App\Http\Controllers\Api\Hl7\RdaManualController;
use App\Http\Controllers\Api\Hl7\CatalogController;

/*
|--------------------------------------------------------------------------
| API Routes for HL7 Interoperability
|--------------------------------------------------------------------------
*/

// Ruta por defecto de Passport para obtener información del usuario autenticado.
Route::get('/user', [AuthController::class, 'user']);

// Endpoint para que los usuarios inicien sesión.
Route::post('/login', [AuthController::class, 'login'])->middleware('client');

// Endpoint para obtener los permisos de un usuario por su ID.
Route::get('/permissions/{usuario_id}', [PermissionController::class, 'getUserPermissions'])->middleware('client');

/*
|--------------------------------------------------------------------------
| HL7 RDA Endpoints (Pharmacy/Treatment Administration)
|--------------------------------------------------------------------------
| Endpoints autenticados para obtener datos de RDA en diferentes contextos.
| Todos requieren autenticación mediante Client Credentials (middleware 'client').
*/

// Endpoint para obtener datos de RDA Paciente.
Route::post('/hl7/rda/paciente', [RdaController::class, 'getRdaPaciente'])->middleware('client');

// Endpoint Dedicado Orquestador de Múltiples RDA
Route::post('/hl7/rda/orquestador', [RdaController::class, 'postRdaOrquestador'])->middleware('client');

// Endpoint para obtener datos de RDA Consulta (En desarrollo).
Route::post('/hl7/rda/consulta', [RdaController::class, 'getRdaConsulta'])->middleware('client');

// Endpoint para obtener datos de RDA Urgencias (En desarrollo).
Route::post('/hl7/rda/urgencias', [RdaController::class, 'getRdaUrgencias'])->middleware('client');

// Endpoint para obtener datos de RDA Hospitalización (En desarrollo).
Route::post('/hl7/rda/hospitalizacion', [RdaController::class, 'getRdaHospitalizacion'])->middleware('client');

// Ruta para guardar el formulario manual de RDA Paciente
Route::post('/hl7/rda/paciente/manual', [RdaManualController::class, 'storePaciente'])->middleware('auth:api');

/*
|--------------------------------------------------------------------------
| Catálogos HL7 — Listas desplegables y autocompletados para el formulario RDA
|--------------------------------------------------------------------------
| Todas protegidas con auth:api (requieren Bearer token de usuario).
| Prefijo: /api/hl7/catalogs/
*/
Route::prefix('hl7/catalogs')->middleware('auth:api')->group(function () {

    // Listas estáticas (catálogos de MinSalud)
    Route::get('/tipos-documento', [CatalogController::class, 'getTiposDocumento']);
    Route::get('/generos',         [CatalogController::class, 'getGeneros']);
    Route::get('/zonas',           [CatalogController::class, 'getZonas']);
    Route::get('/municipios',      [CatalogController::class, 'getMunicipios']);
    Route::get('/unidades-medida', [CatalogController::class, 'getUnidadesMedida']);
    Route::get('/vias-administracion', [CatalogController::class, 'getViasAdministracion']);
    Route::get('/tipos-alergia',   [CatalogController::class, 'getTiposAlergia']);
    Route::get('/parentescos',     [CatalogController::class, 'getParentescos']);
    Route::get('/severidad',       [CatalogController::class, 'getSeveridad']);

    // Búsquedas dinámicas para autocompletado (?q=término, mínimo 2 caracteres)
    Route::get('/search/diagnosticos',  [CatalogController::class, 'searchDiagnosticos']);
    Route::get('/search/medicamentos',  [CatalogController::class, 'searchMedicamentos']);
});

/*
|--------------------------------------------------------------------------
| System Variables Endpoints
|--------------------------------------------------------------------------
*/
Route::post('/hl7/system/variables', [SystemVariablesController::class, 'getVariable'])->middleware('client');
// Endpoint para obtener los tipos de identificación de pacientes.
Route::get('/hl7/system/tipos-id-pacientes', [SystemVariablesController::class, 'getTiposIdPacientes'])->middleware('client');
// Endpoint para obtener los estados de envío de IHCE.
Route::get('/hl7/system/ihce-cat-estados-envio', [SystemVariablesController::class, 'getIhceCatEstadosEnvio'])->middleware('client');
// Endpoint para obtener los tipos de RDA.
Route::get('/hl7/system/ihce-cat-tipos-rda', [SystemVariablesController::class, 'getIhceCatTiposRda'])->middleware('client');
// Endpoint para obtener el último JSON enviado al Ministerio para un ingreso.
Route::post('/hl7/system/ihce-ultimo-json-enviado', [SystemVariablesController::class, 'getUltimoJsonEnviado'])->middleware('client');
// Endpoint para obtener la última respuesta recibida del Ministerio para un ingreso.
Route::post('/hl7/system/ihce-ultima-respuesta-envio', [SystemVariablesController::class, 'getUltimaRespuestaEnvio'])->middleware('client');

/*
|--------------------------------------------------------------------------
| IHCE Audit Endpoints
|--------------------------------------------------------------------------
*/
Route::post('/hl7/auditoria-acceso-link', [AuditoriaAccesoLinkController::class, 'store'])->middleware('client');

/*
|--------------------------------------------------------------------------
| Ingresos Endpoints
|--------------------------------------------------------------------------
*/
Route::post('/hl7/lista-ingreso', [ListaIngresosController::class, 'income'])->middleware('client');

/*
|--------------------------------------------------------------------------
| Consultas al Ministerio IHCE
|--------------------------------------------------------------------------
| Endpoints para consultar información de pacientes directamente en el
| API del Ministerio. Usan el mismo token OAuth (OAuthTokenService)
| y las credenciales IHCE ya configuradas en .env.
*/
Route::prefix('hl7/consulta-ministerio')->middleware('client')->group(function () {
    // Auditoría de visualización de RDAs.
    // POST /api/hl7/consulta-ministerio/audit/rda-view
    Route::post('/audit/rda-view', [RdaAuditController::class, 'storeRdaView']);

    // Consulta el RDA de un paciente por tipo y número de documento.
    // POST /api/hl7/consulta-ministerio/rda-paciente
    Route::post('/rda-paciente', [ConsultaMinisterioController::class, 'consultarRdaPaciente']);

    // Consulta los encuentros clínicos del paciente (humanuser opcional para auditoría).
    // POST /api/hl7/consulta-ministerio/rda-encuentros-clinicos
    Route::post('/rda-encuentros-clinicos', [ConsultaMinisterioController::class, 'consultarRdaEncuentrosClinicos']);

    // Consulta los datos exactos de un paciente en el Ministerio (/Patient/).
    // POST /api/hl7/consulta-ministerio/paciente-exacto
    Route::post('/paciente-exacto', [ConsultaMinisterioController::class, 'consultarPacienteExacto']);

    // Consulta dinámica de cualquier recurso FHIR por su ruta FHIR completa (GET al Ministerio).
    // POST /api/hl7/consulta-ministerio/recurso
    // Body: { "resource_path": "/Patient/d30a6eb6-a31b-89e1-e157-58c0bfd196e4" }
    Route::post('/recurso', [ConsultaMinisterioController::class, 'consultarRecursoPorId']);

    // Busca pacientes similares en el Ministerio IHCE (búsqueda aproximada).
    // POST /api/hl7/consulta-ministerio/paciente-similar
    Route::post('/paciente-similar', [ConsultaMinisterioController::class, 'consultarPacienteSimilar']);

    // Consulta encuentros clínicos del paciente con filtros de fecha opcionales.
    // POST /api/hl7/consulta-ministerio/rda-encuentros-clinicos-fechas
    // Fechas opcionales: lastUpdated_start, lastUpdated_end, authoredOn_start, authoredOn_end (formato YYYY-MM-DD)
    Route::post('/rda-encuentros-clinicos-fechas', [ConsultaMinisterioController::class, 'consultarRdaEncuentrosClinicosFechas']);

    // Proxy Gateway — consulta un documento externo del Ministerio IHCE por su URL completa.
    // POST /api/hl7/consulta-ministerio/documento-externo
    // Body: { "url": "https://sandbox.ihcecol.gov.co/ihce/DocumentReference/{uuid}/0" }
    // La URL se consume SIN concatenar ninguna variable de entorno.
    Route::post('/documento-externo', [ConsultaMinisterioController::class, 'consultarDocumentoExterno']);
});
