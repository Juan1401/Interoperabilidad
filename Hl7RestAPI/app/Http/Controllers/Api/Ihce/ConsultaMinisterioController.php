<?php

namespace App\Http\Controllers\Api\Ihce;

use App\Http\Controllers\Controller;
use App\Services\Ihce\ConsultaMinisterioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para todos los endpoints de Consulta al Ministerio IHCE.
 *
 * Sigue el mismo patrón que RdaController:
 * validar request → llamar servicio → responder JSON estructurado.
 *
 * Ruta base: /api/hl7/consulta-ministerio
 */
class ConsultaMinisterioController extends Controller
{
    /**
     * @var ConsultaMinisterioService
     */
    protected ConsultaMinisterioService $consultaService;

    /**
     * Constructor — inyección de dependencias.
     */
    public function __construct(ConsultaMinisterioService $consultaService)
    {
        $this->consultaService = $consultaService;
    }

    // =========================================================================
    // Endpoints de Consulta
    // =========================================================================

    /**
     * Consulta el Resumen Digital de Atención (RDA) de un paciente en el Ministerio IHCE.
     *
     * POST /api/hl7/consulta-ministerio/rda-paciente
     *
     * Body JSON esperado:
     * {
     *   "tipo_documento":   "CC",          -- Requerido. Tipo de documento del paciente.
     *   "numero_documento": "123456789"    -- Requerido. Número de identificación.
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function consultarRdaPaciente(Request $request): JsonResponse
    {
        // 1. Validar parámetros: paciente requerido, usuario opcional
        $validated = $request->validate([
            'tipo_documento'     => 'required|string|max:10',
            'numero_documento'   => 'required|string|max:30',
            'tipo_doc_usuario'   => 'nullable|string|max:10',
            'numero_doc_usuario' => 'nullable|string|max:30',
        ]);

        $tipoDocumento    = strtoupper(trim($validated['tipo_documento']));
        $numeroDocumento  = trim($validated['numero_documento']);
        // Opcionales: null si no vienen en el request
        $tipoDocUsuario   = !empty($validated['tipo_doc_usuario'])   ? strtoupper(trim($validated['tipo_doc_usuario']))   : null;
        $numeroDocUsuario = !empty($validated['numero_doc_usuario'])  ? trim($validated['numero_doc_usuario'])             : null;

        Log::info("[ConsultaMinisterioController] Solicitud consultarRdaPaciente — Tipo: {$tipoDocumento}, Doc: {$numeroDocumento}");

        try {
            // 2. Delegar al servicio (humanuser se incluye solo si usuario viene)
            $result = $this->consultaService->consultarRdaPaciente(
                $tipoDocumento,
                $numeroDocumento,
                $tipoDocUsuario,
                $numeroDocUsuario
            );

            $httpStatus  = $result['status_code'] > 0 ? $result['status_code'] : 500;
            $message     = $result['success']
                ? 'Consulta RDA Paciente exitosa.'
                : 'El Ministerio rechazó o no encontró datos para los parámetros indicados.';

            return response()->json([
                'status'  => $httpStatus,
                'success' => $result['success'],
                'message' => $message,
                'data'    => $result['response'],
            ], 200);

        } catch (\Exception $e) {
            Log::error('[ConsultaMinisterioController] Error en consultarRdaPaciente: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());

            $debugInfo = null;
            if (config('app.debug')) {
                $debugInfo = [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => collect(explode("\n", $e->getTraceAsString()))
                        ->take(10)
                        ->values()
                        ->toArray(),
                ];
            }

            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Ocurrió un error interno al procesar la consulta: ' . $e->getMessage(),
                'data'    => null,
                'error'   => $debugInfo,
            ], 500);
        }
    }

    // =========================================================================
    // PUNTO DE EXTENSIÓN: agregar aquí los próximos endpoints de consulta
    // =========================================================================

    /**
     * Consulta los encuentros clínicos de un paciente en el Ministerio IHCE.
     *
     * POST /api/hl7/consulta-ministerio/rda-encuentros-clinicos
     *
     * Body JSON esperado:
     * {
     *   "tipo_documento":      "CC",       -- Tipo de doc del PACIENTE (requerido)
     *   "numero_documento":    "123456789",-- Núm de doc del PACIENTE (requerido)
     *   "tipo_doc_usuario":    "CC",       -- Tipo de doc del USUARIO que consulta (requerido)
     *   "numero_doc_usuario":  "987654321" -- Núm de doc del USUARIO que consulta (requerido)
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function consultarRdaEncuentrosClinicos(Request $request): JsonResponse
    {
        // 1. Validar parámetros: paciente requerido, usuario opcional
        $validated = $request->validate([
            'tipo_documento'     => 'required|string|max:10',
            'numero_documento'   => 'required|string|max:30',
            'tipo_doc_usuario'   => 'nullable|string|max:10',
            'numero_doc_usuario' => 'nullable|string|max:30',
        ]);

        $tipoDocumento    = strtoupper(trim($validated['tipo_documento']));
        $numeroDocumento  = trim($validated['numero_documento']);
        // Opcionales: null si no vienen en el request
        $tipoDocUsuario   = !empty($validated['tipo_doc_usuario'])   ? strtoupper(trim($validated['tipo_doc_usuario']))   : null;
        $numeroDocUsuario = !empty($validated['numero_doc_usuario'])  ? trim($validated['numero_doc_usuario'])             : null;

        Log::info(
            "[ConsultaMinisterioController] Solicitud consultarRdaEncuentrosClinicos — " .
            "Paciente: {$tipoDocumento}/{$numeroDocumento} — " .
            "Usuario: {$tipoDocUsuario}/{$numeroDocUsuario}"
        );

        try {
            // 2. Delegar al servicio
            $result = $this->consultaService->consultarRdaEncuentrosClinicos(
                $tipoDocumento,
                $numeroDocumento,
                $tipoDocUsuario,
                $numeroDocUsuario
            );

            $httpStatus = $result['status_code'] > 0 ? $result['status_code'] : 500;
            $message    = $result['success']
                ? 'Consulta Encuentros Clínicos exitosa.'
                : 'El Ministerio rechazó o no encontró datos para los parámetros indicados.';

            return response()->json([
                'status'  => $httpStatus,
                'success' => $result['success'],
                'message' => $message,
                'data'    => $result['response'],
            ], 200);

        } catch (\Exception $e) {
            Log::error('[ConsultaMinisterioController] Error en consultarRdaEncuentrosClinicos: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());

            $debugInfo = null;
            if (config('app.debug')) {
                $debugInfo = [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => collect(explode("\n", $e->getTraceAsString()))
                        ->take(10)
                        ->values()
                        ->toArray(),
                ];
            }

            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Ocurrió un error interno al procesar la consulta: ' . $e->getMessage(),
                'data'    => null,
                'error'   => $debugInfo,
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EJEMPLO — Consultar RDA Urgencias (pendiente de implementar)
    |--------------------------------------------------------------------------
    |
    | public function consultarRdaUrgencias(Request $request): JsonResponse
    | {
    |     $validated = $request->validate([
    |         'tipo_documento'     => 'required|string|max:10',
    |         'numero_documento'   => 'required|string|max:30',
    |         'tipo_doc_usuario'   => 'required|string|max:10',
    |         'numero_doc_usuario' => 'required|string|max:30',
    |     ]);
    |     ...
    |     $result = $this->consultaService->consultarRdaUrgencias(...);
    |     ...
    | }
    |
    */

    /**
     * Consulta los datos exactos de un paciente en el Ministerio IHCE.
     *
     * POST /api/hl7/consulta-ministerio/paciente-exacto
     *
     * Body JSON esperado:
     * {
     *   "tipo_documento":     "RC",        -- Tipo de doc del PACIENTE (requerido)
     *   "numero_documento":   "1112070642", -- Núm de doc del PACIENTE (requerido)
     *   "tipo_doc_usuario":   "CC",         -- Tipo de doc del USUARIO (opcional)
     *   "numero_doc_usuario": "123123"      -- Núm de doc del USUARIO (opcional)
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function consultarPacienteExacto(Request $request): JsonResponse
    {
        // 1. Validar parámetros: paciente requerido, usuario opcional
        $validated = $request->validate([
            'tipo_documento'     => 'required|string|max:10',
            'numero_documento'   => 'required|string|max:30',
            'tipo_doc_usuario'   => 'nullable|string|max:10',
            'numero_doc_usuario' => 'nullable|string|max:30',
        ]);

        $tipoDocumento    = strtoupper(trim($validated['tipo_documento']));
        $numeroDocumento  = trim($validated['numero_documento']);
        $tipoDocUsuario   = !empty($validated['tipo_doc_usuario'])   ? strtoupper(trim($validated['tipo_doc_usuario']))  : null;
        $numeroDocUsuario = !empty($validated['numero_doc_usuario'])  ? trim($validated['numero_doc_usuario'])            : null;

        Log::info(
            "[ConsultaMinisterioController] Solicitud consultarPacienteExacto — " .
            "Tipo: {$tipoDocumento}, Doc: {$numeroDocumento}"
        );

        try {
            // 2. Delegar al servicio
            $result = $this->consultaService->consultarPacienteExacto(
                $tipoDocumento,
                $numeroDocumento,
                $tipoDocUsuario,
                $numeroDocUsuario
            );

            $httpStatus = $result['status_code'] > 0 ? $result['status_code'] : 500;
            $message    = $result['success']
                ? 'Consulta Paciente Exacto exitosa.'
                : 'El Ministerio rechazó o no encontró datos para los parámetros indicados.';

            return response()->json([
                'status'  => $httpStatus,
                'success' => $result['success'],
                'message' => $message,
                'data'    => $result['response'],
            ], 200);

        } catch (\Exception $e) {
            Log::error('[ConsultaMinisterioController] Error en consultarPacienteExacto: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());

            $debugInfo = null;
            if (config('app.debug')) {
                $debugInfo = [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => collect(explode("\n", $e->getTraceAsString()))
                        ->take(10)
                        ->values()
                        ->toArray(),
                ];
            }

            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Ocurrió un error interno al procesar la consulta: ' . $e->getMessage(),
                'data'    => null,
                'error'   => $debugInfo,
            ], 500);
        }
    }

    /**
     * Consulta un recurso FHIR por su ruta completa en el Ministerio IHCE.
     *
     * POST /api/hl7/consulta-ministerio/recurso
     *
     * Recibe la ruta FHIR completa en el body y realiza un GET al Ministerio.
     * Ejemplos de resource_path válidos:
     *   - /Patient/d30a6eb6-a31b-89e1-e157-58c0bfd196e4
     *   - /Practitioner/10c21843-4267-da2e-6a50-500b51881043
     *   - /Condition/87cd1795-afbe-4992-be3d-695c0fd180cd
     *
     * Body JSON esperado:
     * {
     *   "resource_path": "/Patient/d30a6eb6-a31b-89e1-e157-58c0bfd196e4"
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function consultarRecursoPorId(Request $request): JsonResponse
    {
        // 1. Validar que venga el resource_path en el body
        $validated = $request->validate([
            'resource_path' => 'required|string|min:3',
        ]);

        // Normalizar: garantizar que empiece con "/"
        $resourcePath = '/' . ltrim(trim($validated['resource_path']), '/');

        Log::info("[ConsultaMinisterioController] Solicitud consultarRecursoPorId — Path: {$resourcePath}");

        try {
            // 2. Delegar al servicio — hace GET /{resource_path} al Ministerio
            $result = $this->consultaService->consultarRecursoPorRuta($resourcePath);

            $httpStatus = $result['status_code'] > 0 ? $result['status_code'] : 500;
            $message    = $result['success']
                ? 'Consulta de recurso FHIR exitosa.'
                : 'El Ministerio no encontró el recurso solicitado.';

            return response()->json([
                'status'        => $httpStatus,
                'success'       => $result['success'],
                'message'       => $message,
                'resource_path' => $resourcePath,
                'data'          => $result['response'],
            ], 200);

        } catch (\Exception $e) {
            Log::error('[ConsultaMinisterioController] Error en consultarRecursoPorId: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());

            $debugInfo = null;
            if (config('app.debug')) {
                $debugInfo = [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => collect(explode("\n", $e->getTraceAsString()))
                        ->take(10)
                        ->values()
                        ->toArray(),
                ];
            }

            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Ocurrió un error interno al procesar la consulta: ' . $e->getMessage(),
                'data'    => null,
                'error'   => $debugInfo,
            ], 500);
        }
    }

    /**
     * Busca pacientes con datos similares (búsqueda aproximada) en el Ministerio IHCE.
     *
     * POST /api/hl7/consulta-ministerio/paciente-similar
     *
     * Body JSON esperado:
     * {
     *   "tipo_documento":     "RC",
     *   "numero_documento":   "1232832630",
     *   "tipo_doc_usuario":   "CC",      // opcional
     *   "numero_doc_usuario": "123123"   // opcional
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function consultarPacienteSimilar(Request $request): JsonResponse
    {
        // 1. Validar parámetros: paciente requerido, usuario opcional
        $validated = $request->validate([
            'tipo_documento'     => 'required|string|max:10',
            'numero_documento'   => 'required|string|max:30',
            'tipo_doc_usuario'   => 'nullable|string|max:10',
            'numero_doc_usuario' => 'nullable|string|max:30',
        ]);

        $tipoDocumento    = strtoupper(trim($validated['tipo_documento']));
        $numeroDocumento  = trim($validated['numero_documento']);
        $tipoDocUsuario   = !empty($validated['tipo_doc_usuario'])   ? strtoupper(trim($validated['tipo_doc_usuario']))  : null;
        $numeroDocUsuario = !empty($validated['numero_doc_usuario'])  ? trim($validated['numero_doc_usuario'])            : null;

        Log::info(
            "[ConsultaMinisterioController] Solicitud consultarPacienteSimilar — " .
            "Tipo: {$tipoDocumento}, Doc: {$numeroDocumento}"
        );

        try {
            // 2. Delegar al servicio
            $result = $this->consultaService->consultarPacienteSimilar(
                $tipoDocumento,
                $numeroDocumento,
                $tipoDocUsuario,
                $numeroDocUsuario
            );

            $httpStatus = $result['status_code'] > 0 ? $result['status_code'] : 500;
            $message    = $result['success']
                ? 'Búsqueda de paciente similar exitosa.'
                : 'El Ministerio rechazó o no encontró datos similares para los parámetros indicados.';

            return response()->json([
                'status'  => $httpStatus,
                'success' => $result['success'],
                'message' => $message,
                'data'    => $result['response'],
            ], 200);

        } catch (\Exception $e) {
            Log::error('[ConsultaMinisterioController] Error en consultarPacienteSimilar: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());

            $debugInfo = null;
            if (config('app.debug')) {
                $debugInfo = [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => collect(explode("\n", $e->getTraceAsString()))
                        ->take(10)
                        ->values()
                        ->toArray(),
                ];
            }

            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Ocurrió un error interno al procesar la consulta: ' . $e->getMessage(),
                'data'    => null,
                'error'   => $debugInfo,
            ], 500);
        }
    }

    /**
     * Consulta los encuentros clínicos de un paciente con filtros de fecha opcionales.
     *
     * POST /api/hl7/consulta-ministerio/rda-encuentros-clinicos-fechas
     *
     * Body JSON esperado:
     * {
     *   "tipo_documento":      "RC",          // requerido
     *   "numero_documento":    "1232832630",  // requerido
     *   "tipo_doc_usuario":    "CC",          // opcional
     *   "numero_doc_usuario":  "123123",      // opcional
     *   "lastUpdated_start":   "2025-08-01",  // opcional (YYYY-MM-DD)
     *   "lastUpdated_end":     "2025-08-31",  // opcional (YYYY-MM-DD)
     *   "authoredOn_start":    "2025-08-01",  // opcional (YYYY-MM-DD)
     *   "authoredOn_end":      "2025-08-31"   // opcional (YYYY-MM-DD)
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function consultarRdaEncuentrosClinicosFechas(Request $request): JsonResponse
    {
        // 1. Validar parámetros: paciente requerido, todo lo demás opcional
        $validated = $request->validate([
            'tipo_documento'     => 'required|string|max:10',
            'numero_documento'   => 'required|string|max:30',
            'tipo_doc_usuario'   => 'nullable|string|max:10',
            'numero_doc_usuario' => 'nullable|string|max:30',
            'lastUpdated_start'  => 'nullable|date_format:Y-m-d',
            'lastUpdated_end'    => 'nullable|date_format:Y-m-d',
            'authoredOn_start'   => 'nullable|date_format:Y-m-d',
            'authoredOn_end'     => 'nullable|date_format:Y-m-d',
        ]);

        $tipoDocumento    = strtoupper(trim($validated['tipo_documento']));
        $numeroDocumento  = trim($validated['numero_documento']);
        $tipoDocUsuario   = !empty($validated['tipo_doc_usuario'])   ? strtoupper(trim($validated['tipo_doc_usuario']))  : null;
        $numeroDocUsuario = !empty($validated['numero_doc_usuario'])  ? trim($validated['numero_doc_usuario'])            : null;
        $lastUpdatedStart = $validated['lastUpdated_start'] ?? null;
        $lastUpdatedEnd   = $validated['lastUpdated_end']   ?? null;
        $authoredOnStart  = $validated['authoredOn_start']  ?? null;
        $authoredOnEnd    = $validated['authoredOn_end']    ?? null;

        Log::info(
            "[ConsultaMinisterioController] Solicitud consultarRdaEncuentrosClinicosFechas — " .
            "Tipo: {$tipoDocumento}, Doc: {$numeroDocumento}"
        );

        try {
            // 2. Delegar al servicio
            $result = $this->consultaService->consultarRdaEncuentrosClinicosFechas(
                $tipoDocumento,
                $numeroDocumento,
                $tipoDocUsuario,
                $numeroDocUsuario,
                $lastUpdatedStart,
                $lastUpdatedEnd,
                $authoredOnStart,
                $authoredOnEnd
            );

            $httpStatus = $result['status_code'] > 0 ? $result['status_code'] : 500;
            $message    = $result['success']
                ? 'Consulta Encuentros Clínicos con Fechas exitosa.'
                : 'El Ministerio rechazó o no encontró datos para los parámetros indicados.';

            return response()->json([
                'status'  => $httpStatus,
                'success' => $result['success'],
                'message' => $message,
                'data'    => $result['response'],
            ], 200);

        } catch (\Exception $e) {
            Log::error('[ConsultaMinisterioController] Error en consultarRdaEncuentrosClinicosFechas: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());

            $debugInfo = null;
            if (config('app.debug')) {
                $debugInfo = [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => collect(explode("\n", $e->getTraceAsString()))
                        ->take(10)
                        ->values()
                        ->toArray(),
                ];
            }

            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Ocurrió un error interno al procesar la consulta: ' . $e->getMessage(),
                'data'    => null,
                'error'   => $debugInfo,
            ], 500);
        }
    }

    /**
     * Proxy Gateway — Consulta un documento externo en el Ministerio IHCE.
     *
     * Recibe en el body una URL **completa** (ya formada) que apunta a un recurso
     * del Ministerio (ej. un DocumentReference con un adjunto PDF) y la consume
     * directamente, SIN concatenar ninguna variable de entorno (APP_URL, IHCE_BASE_URL, etc.).
     *
     * POST /api/hl7/consulta-ministerio/documento-externo
     *
     * Body JSON:
     * {
     *   "url": "https://sandbox.ihcecol.gov.co/ihce/DocumentReference/bc62cfab-aa66-424e-af33-6cc4e277f953/0"
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function consultarDocumentoExterno(Request $request): JsonResponse
    {
        // 1. Validar el campo requerido con formato de URL estricto.
        try {
            $request->validate([
                'url' => 'required|url',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => 422,
                'success' => false,
                'message' => 'Parámetros inválidos: se requiere una URL válida en el campo "url".',
                'errors'  => $e->errors(),
                'data'    => null,
            ], 422);
        }

        $fullUrl = trim($request->input('url'));

        Log::info("[ConsultaMinisterioController] Proxy documento externo → {$fullUrl}");

        // 2. Delegar al servicio — la URL llega y se consume exactamente como viene.
        try {
            $result = $this->consultaService->consultarDocumentoExterno($fullUrl);

            $httpStatus = $result['status_code'] ?? 500;
            $success    = $result['success']     ?? false;

            if ($success) {
                return response()->json([
                    'status'  => $httpStatus,
                    'success' => true,
                    'message' => 'Documento obtenido correctamente.',
                    'data'    => $result['response'],
                ], 200);
            }

            // La petición al Ministerio falló (4xx / 5xx).
            return response()->json([
                'status'  => $httpStatus,
                'success' => false,
                'message' => 'El servidor del Ministerio IHCE devolvió un error al consultar el documento.',
                'data'    => $result['response'] ?? null,
            ], $httpStatus >= 400 ? $httpStatus : 502);

        } catch (\Throwable $e) {
            Log::error(
                '[ConsultaMinisterioController] Error inesperado en consultarDocumentoExterno: ' .
                $e->getMessage(),
                ['url' => $fullUrl, 'trace' => $e->getTraceAsString()]
            );

            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Error interno al contactar el servidor del Ministerio: ' . $e->getMessage(),
                'data'    => null,
            ], 500);
        }
    }
}
