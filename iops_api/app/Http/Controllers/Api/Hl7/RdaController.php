<?php

namespace App\Http\Controllers\Api\Hl7;

use App\Http\Controllers\Controller;
use App\Services\Hl7\RdaPacienteService;
use App\Services\Hl7\RdaConsultaService;
use App\Services\Hl7\RdaUrgenciasService;
use App\Services\Hl7\RdaHospitalizacionService;
use App\Services\Hl7\RdaRouterService;
use App\Services\OAuthTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

/**
 * Controlador para endpoints de HL7 RDA (Pharmacy/Treatment Administration)
 */
class RdaController extends Controller
{
    /**
     * @var OAuthTokenService
     */
    protected $oauthTokenService;

    /**
     * @var RdaPacienteService
     */
    protected $rdaPacienteService;

    /**
     * @var RdaConsultaService
     */
    protected $rdaConsultaService;

    /**
     * @var RdaUrgenciasService
     */
    protected $rdaUrgenciasService;

    /**
     * @var RdaHospitalizacionService
     */
    protected $rdaHospitalizacionService;

    /**
     * @var RdaRouterService
     */
    protected $rdaRouterService;

    /**
     * Constructor del controlador.
     * Inyección de dependencias de servicios.
     */
    public function __construct(
        OAuthTokenService $oauthTokenService,
        RdaPacienteService $rdaPacienteService,
        RdaConsultaService $rdaConsultaService,
        RdaUrgenciasService $rdaUrgenciasService,
        RdaHospitalizacionService $rdaHospitalizacionService,
        RdaRouterService $rdaRouterService
    ) {
        $this->oauthTokenService = $oauthTokenService;
        $this->rdaPacienteService = $rdaPacienteService;
        $this->rdaConsultaService = $rdaConsultaService;
        $this->rdaUrgenciasService = $rdaUrgenciasService;
        $this->rdaHospitalizacionService = $rdaHospitalizacionService;
        $this->rdaRouterService = $rdaRouterService;
    }

    /**
     * Endpoint para obtener datos de RDA Paciente.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getRdaPaciente(Request $request): JsonResponse
    {
        // Validar los parámetros requeridos
        $validated = $request->validate([
            'ingreso' => 'required|integer|min:1',
            'usuario_id' => 'required|integer|min:1',
        ]);

        $ingresoId = $validated['ingreso'];
        $usuarioId = $validated['usuario_id'];

        try {
            Log::info("Solicitud RDA Paciente recibida para ingreso: {$ingresoId} por usuario: {$usuarioId}");

            // Obtener los datos a través del servicio
            $rdaDataPaciente = $this->rdaPacienteService->getDataForRda($ingresoId);

            // Validar que los datos sean suficientes (e.g. Bundle payload)
            if (!$this->rdaPacienteService->validateRdaData($rdaDataPaciente)) {
                return response()->json([
                    'status' => 422,
                    'success' => false,
                    'message' => 'Los datos del ingreso son insuficientes para generar RDA.',
                    'data' => null,
                ], 422);
            }

            // Obtener Token de IHCE Ministerio (Persistido en caché por 55 minutos)
            $tokenIhce = $this->oauthTokenService->getToken();

            // Envio del RDA Paciente al API IHCE Ministerio
            $ministerioResponse = $this->rdaPacienteService->sendRdaPaciente($rdaDataPaciente, $tokenIhce, $ingresoId);

            // En esta linea se almacena en la base de datos el envio del RDA Paciente
            $httpStatus = $ministerioResponse['status_code'] > 0 ? $ministerioResponse['status_code'] : 500;

            // Patrón Master-Detail para trazabilidad (1 = RDA Paciente)
            $this->rdaPacienteService->logEnvioRda(
                $ingresoId,
                $usuarioId,
                1, // tipo_rda_id (RDA_PACIENTE)
                $rdaDataPaciente,
                $ministerioResponse['response'],
                $httpStatus
            );

            // Preparamos el mensaje base de éxito/rechazo
            $baseMessage = $ministerioResponse['success']
                ? 'Datos RDA Paciente obtenidos y enviados exitosamente al Ministerio.'
                : 'Aviso: El envío al Ministerio fue rechazado/fallido.';

            return response()->json([
                'status' => $httpStatus,
                'success' => $ministerioResponse['success'],
                'message' => $baseMessage,
                'data' => $rdaDataPaciente,
                'ministerio_response' => $ministerioResponse['response']
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en RdaController::getRdaPaciente: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            $debugInfo = null;
            if (config('app.debug')) {
                $debugInfo = [
                    'message'    => $e->getMessage(),
                    'file'       => $e->getFile(),
                    'line'       => $e->getLine(),
                    'trace'      => collect(explode("\n", $e->getTraceAsString()))
                        ->take(10)
                        ->values()
                        ->toArray(),
                ];
            }

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage(),
                'data' => null,
                'error' => $debugInfo,
            ], 500);
        }
    }

    /**
     * Endpoint Orquestador dedicado para múltiples RDA.
     * Manda el RDA Paciente, y si resulta exitoso, orquesta internamente el RDA Secundario.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function postRdaOrquestador(Request $request): JsonResponse
    {
        // 1. Validar request inicial obligatoria
        $validated = $request->validate([
            'ingreso' => 'required|integer|min:1',
            'usuario_id' => 'required|integer|min:1',
        ]);
        $ingresoId = $validated['ingreso'];

        // 2. Ejecutar primero la rutina de RDA Paciente (como sub-request interno)
        $pacienteResponse = $this->getRdaPaciente($request);
        $pacienteData = json_decode($pacienteResponse->getContent(), true);

        // Si falló el primer RDA, devolvemos inmediatamente sin orquestar el segundo
        if ($pacienteResponse->getStatusCode() !== 200 || !($pacienteData['success'] ?? false)) {
            return $pacienteResponse; // Devuelve el error tal cual falló en getRdaPaciente
        }

        // 3. Comenzar la Orquestación del Segundo RDA
        $secondRdaResponse = null;

        try {
            // A. Determina la clase de atención (EMER, IMP, AMB)
            $claseAtencion = $this->rdaRouterService->determinarClaseAtencion($ingresoId);

            // B. Obtiene qué método interno procesa ese RDA
            $metodoDestino = $this->rdaRouterService->obtenerRutaDestino($claseAtencion);

            Log::info("Orquestando RDA Secundario hacia: {$metodoDestino} para el ingreso: {$ingresoId}");

            // C. LLAMADA INTERNA al Endpoint Secundario (reutilizando el `$request`)
            $subResponse = $this->{$metodoDestino}($request);

            // D. Transformar JSON interno a un Array
            if ($subResponse instanceof JsonResponse) {
                $secondRdaResponse = json_decode($subResponse->getContent(), true);
            } else {
                $secondRdaResponse = ['error' => 'La respuesta interna no es un JSON válido.'];
            }
        } catch (\Exception $e) {
            Log::error("Fallo orquestando el RDA Secundario para {$ingresoId}: " . $e->getMessage());
            $secondRdaResponse = [
                'status' => 500,
                'success' => false,
                'message' => 'Error interno al orquestar: ' . $e->getMessage()
            ];
        }

        // 4. Adjuntar la respuesta del Segundo RDA al Objeto del Paciente y devolverlo combinado
        $pacienteData = is_array($secondRdaResponse) ? $secondRdaResponse : [];

        return response()->json($pacienteData, 200);
    }

    /**
     * Endpoint para obtener datos de RDA Consulta.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getRdaConsulta(Request $request): JsonResponse
    {
        // Validar los parámetros requeridos
        $validated = $request->validate([
            'ingreso' => 'required|integer|min:1',
            'usuario_id' => 'required|integer|min:1',
        ]);

        $ingresoId = $validated['ingreso'];
        $usuarioId = $validated['usuario_id'];

        try {
            Log::info("Solicitud RDA Consulta recibida para ingreso: {$ingresoId}");
            $rdaDataConsulta = $this->rdaConsultaService->getDataForRda($ingresoId);

            // Validar que los datos sean suficientes (e.g. Bundle payload)
            if (!$this->rdaConsultaService->validateRdaData($rdaDataConsulta)) {
                return response()->json([
                    'status' => 422,
                    'success' => false,
                    'message' => 'Los datos del ingreso son insuficientes para generar RDA.',
                    'data' => null,
                ], 422);
            }

            // Obtener Token de IHCE Ministerio (Persistido en caché por 55 minutos)
            $tokenIhce = $this->oauthTokenService->getToken();

            // Envio del RDA Consulta al API IHCE Ministerio
            $ministerioResponse = $this->rdaConsultaService->sendRdaConsulta($rdaDataConsulta, $tokenIhce, $ingresoId);

            // En esta linea se almacena en la base de datos el envio del RDA Consulta
            $httpStatus = $ministerioResponse['status_code'] > 0 ? $ministerioResponse['status_code'] : 500;

            // echo json_encode($ministerioResponse, JSON_PRETTY_PRINT);
            // die();

            // echo "\n" . "<b><br> ministerioResponse[]<pre>" . "\n";
            // print_r($ministerioResponse);
            // echo "\n" . "</pre></b>" . "\n";
            // die("\n" . "<br><b>Archivo Modificado:<br>" . __FILE__ . "</b><br>Error Desarrollo " . date("d-m-Y H:i:s") . "\n");


            // Patrón Master-Detail para trazabilidad (3 = RDA Consulta)
            $this->rdaConsultaService->logEnvioRda(
                $ingresoId,
                $usuarioId,
                3, // tipo_rda_id (RDA_CONSULTA)
                $rdaDataConsulta,
                $ministerioResponse['response'],
                $httpStatus
            );

            // Preparamos el mensaje base de éxito/rechazo
            $baseMessage = $ministerioResponse['success']
                ? 'Datos RDA Consulta obtenidos y enviados exitosamente al Ministerio.'
                : 'Aviso: El envío al Ministerio fue rechazado/fallido.';

            return response()->json([
                'status' => $httpStatus,
                'success' => $ministerioResponse['success'],
                'message' => $baseMessage,
                'data' => $rdaDataConsulta,
                'ministerio_response' => $ministerioResponse['response']
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en RdaController::getRdaConsulta: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            $debugInfo = null;
            if (config('app.debug')) {
                $debugInfo = [
                    'message'    => $e->getMessage(),
                    'file'       => $e->getFile(),
                    'line'       => $e->getLine(),
                    'trace'      => collect(explode("\n", $e->getTraceAsString()))
                        ->take(10)
                        ->values()
                        ->toArray(),
                ];
            }

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage(),
                'data' => null,
                'error' => $debugInfo,
            ], 500);
        }
    }

    /**
     * Endpoint para obtener, validar y enviar RDA de Urgencias al Ministerio.
     * Sigue el mismo patrón que getRdaPaciente: validar → token → enviar → log.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getRdaUrgencias(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ingreso' => 'required|integer|min:1',
            'usuario_id' => 'required|integer|min:1',
        ]);

        $ingresoId = $validated['ingreso'];
        $usuarioId = $validated['usuario_id'];

        try {
            Log::info("Solicitud RDA Urgencias recibida para ingreso: {$ingresoId} por usuario: {$usuarioId}");

            // 1. Obtener los datos a través del servicio (genera el Bundle FHIR)
            $rdaDataUrgencias = $this->rdaUrgenciasService->getDataForRda($ingresoId);

            // 2. Validar que los datos sean suficientes
            if (!$this->rdaUrgenciasService->validateRdaData($rdaDataUrgencias)) {
                return response()->json([
                    'status' => 422,
                    'success' => false,
                    'message' => 'Los datos del ingreso son insuficientes para generar RDA Urgencias.',
                    'data' => null,
                ], 422);
            }

            // 3. Obtener Token de IHCE Ministerio (persistido en caché por 55 minutos)
            $tokenIhce = $this->oauthTokenService->getToken();

            // 4. Envío del RDA Urgencias al API IHCE Ministerio
            $ministerioResponse = $this->rdaUrgenciasService->sendRdaUrgencias($rdaDataUrgencias, $tokenIhce, $ingresoId);

            $httpStatus = $ministerioResponse['status_code'] > 0 ? $ministerioResponse['status_code'] : 500;

            // 5. Patrón Master-Detail para trazabilidad (2 = RDA Urgencias)
            $this->rdaUrgenciasService->logEnvioRda(
                $ingresoId,
                $usuarioId,
                2, // tipo_rda_id (RDA_URGENCIAS)
                $rdaDataUrgencias,
                $ministerioResponse['response'],
                $httpStatus
            );

            // 6. Preparar respuesta
            $baseMessage = $ministerioResponse['success']
                ? 'Datos RDA Urgencias obtenidos y enviados exitosamente al Ministerio.'
                : 'Aviso: El envío al Ministerio fue rechazado/fallido.';

            return response()->json([
                'status' => $httpStatus,
                'success' => $ministerioResponse['success'],
                'message' => $baseMessage,
                'data' => $rdaDataUrgencias,
                'ministerio_response' => $ministerioResponse['response']
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en RdaController::getRdaUrgencias: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            $debugInfo = null;
            if (config('app.debug')) {
                $debugInfo = [
                    'message'    => $e->getMessage(),
                    'file'       => $e->getFile(),
                    'line'       => $e->getLine(),
                    'trace'      => collect(explode("\n", $e->getTraceAsString()))
                        ->take(10)
                        ->values()
                        ->toArray(),
                ];
            }

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage(),
                'data' => null,
                'error' => $debugInfo,
            ], 500);
        }
    }

    /**
     * Endpoint para obtener, enviar y loguear datos de RDA Hospitalización.
     * Sigue el mismo patrón que getRdaPaciente: validar → token → enviar → log.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getRdaHospitalizacion(Request $request): JsonResponse
    {
        // 1. Validar request (incluimos usuario_id para la trazabilidad)
        $validated = $request->validate([
            'ingreso'    => 'required|integer|min:1',
            'usuario_id' => 'required|integer|min:1',
        ]);

        $ingresoId = $validated['ingreso'];
        $usuarioId = $validated['usuario_id'];

        try {
            Log::info("Solicitud RDA Hospitalización recibida para ingreso: {$ingresoId} por usuario: {$usuarioId}");

            // 2. Obtener los datos a través del servicio (genera el Bundle FHIR de Hospitalización)
            $rdaDataHospital = $this->rdaHospitalizacionService->getDataForRda($ingresoId);

            // 3. Validar que los datos estructurales mínimos existan
            if (!$this->rdaHospitalizacionService->validateRdaData($rdaDataHospital)) {
                return response()->json([
                    'status'  => 422,
                    'success' => false,
                    'message' => 'Los datos del ingreso son insuficientes para generar el Bundle de Hospitalización.',
                    'data'    => null,
                ], 422);
            }

            // 4. Obtener Token de IHCE Ministerio
            $tokenIhce = $this->oauthTokenService->getToken();

            // 5. Envío del RDA Hospitalización al API del Ministerio
            // Nota: Asegúrate de que el método sendRdaHospitalizacion esté definido en tu RdaHospitalizacionService
            $ministerioResponse = $this->rdaHospitalizacionService->sendRdaHospitalizacion($rdaDataHospital, $tokenIhce, $ingresoId);

            $httpStatus = $ministerioResponse['status_code'] > 0 ? $ministerioResponse['status_code'] : 500;

            // 6. Registro de trazabilidad (4 = RDA Hospitalización)
            $this->rdaHospitalizacionService->logEnvioRda(
                $ingresoId,
                $usuarioId,
                4, // tipo_rda_id para Hospitalización
                $rdaDataHospital,
                $ministerioResponse['response'],
                $httpStatus
            );

            // 7. Preparar respuesta final
            $baseMessage = $ministerioResponse['success']
                ? 'RDA Hospitalización procesado y enviado exitosamente al Ministerio.'
                : 'Aviso: El envío de Hospitalización al Ministerio falló o fue rechazado.';

            return response()->json([
                'status'              => $httpStatus,
                'success'             => $ministerioResponse['success'],
                'message'             => $baseMessage,
                'data'                => $rdaDataHospital,
                'ministerio_response' => $ministerioResponse['response']
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en RdaController::getRdaHospitalizacion: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());

            $debugInfo = null;
            if (config('app.debug')) {
                $debugInfo = [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => collect(explode("\n", $e->getTraceAsString()))->take(5)->toArray(),
                ];
            }

            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Error crítico en el orquestador de Hospitalización: ' . $e->getMessage(),
                'error'   => $debugInfo,
            ], 500);
        }
    }
}