<?php

namespace App\Services\Ihce;

use App\Services\OAuthTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio centralizado para TODAS las consultas al API del Ministerio IHCE.
 *
 * Cada método representa un endpoint de consulta distinto del Sandbox/Producción.
 * Se reutiliza el OAuthTokenService (token cacheado 55 min) y las
 * credenciales IHCE_BASE_URL / IHCE_SUBSCRIPTION_KEY del .env.
 *
 * Contrato de retorno estándar para todos los métodos:
 * [
 *     'success'     => bool,
 *     'status_code' => int,
 *     'response'    => array,
 * ]
 */
class ConsultaMinisterioService
{
    /**
     * @var OAuthTokenService
     */
    protected OAuthTokenService $oauthTokenService;

    /**
     * URL base del Ministerio IHCE (desde .env / config/services.php).
     * Ejemplo: https://sandbox.ihcecol.gov.co/ihce
     *
     * @var string
     */
    protected string $baseUrl;

    /**
     * Llave de suscripción de Azure API Management.
     * Ejemplo: Ocp-Apim-Subscription-Key
     *
     * @var string
     */
    protected string $subscriptionKey;

    /**
     * Constructor — inyección de dependencias PSR.
     */
    public function __construct(OAuthTokenService $oauthTokenService)
    {
        $this->oauthTokenService = $oauthTokenService;
        $config                  = config('services.ihce');
        $this->baseUrl           = rtrim($config['base_url'], '/');
        $this->subscriptionKey   = $config['subscription_key'];
    }

    // =========================================================================
    // Métodos privados de soporte
    // =========================================================================

    /**
     * Construye los headers estándar para todas las peticiones al Ministerio.
     *
     * @param string $token Bearer token OAuth.
     * @return array
     */
    private function buildHeaders(string $token): array
    {
        return [
            'Authorization'            => "Bearer {$token}",
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            'Content-Type'             => 'application/json',
            'Accept'                   => 'application/json',
        ];
    }

    /**
     * Realiza una petición POST al Ministerio y retorna el resultado normalizado.
     *
     * @param string $endpoint Endpoint relativo. Ej: '/Composition/$consultar-rda-paciente'
     * @param array  $payload  Cuerpo FHIR en formato array PHP.
     * @return array ['success' => bool, 'status_code' => int, 'response' => array]
     */
    private function postMinisterio(string $endpoint, array $payload): array
    {
        $token      = $this->oauthTokenService->getToken();
        $url        = $this->baseUrl . $endpoint;
        $success    = false;
        $statusCode = 500;
        $response   = [];

        Log::info("[ConsultaMinisterioService] POST → {$url}");

        // Usamos CURL nativo porque el Ministerio devuelve el header
        // 'Cache Control' (con espacio), que Guzzle rechaza como inválido.
        $headers = $this->buildHeaders($token);
        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = "{$name}: {$value}";
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => $curlHeaders,
        ]);

        $body      = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            Log::error("[ConsultaMinisterioService] CURL POST error en {$url}: {$curlError}");
            $response = ['error_interno' => $curlError];
        } else {
            $success = ($statusCode >= 200 && $statusCode < 300);
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                $response = $decoded;
            } else {
                $response = ['raw_body' => $body];
                Log::warning("[ConsultaMinisterioService] Respuesta no-JSON POST (HTTP {$statusCode}): " . substr($body, 0, 300));
            }

            if ($statusCode === 401) {
                Log::warning("[ConsultaMinisterioService] Token IHCE rechazado (401). Limpiando caché.");
                Cache::forget('ihce_oauth_token');
            }

            Log::info("[ConsultaMinisterioService] Respuesta HTTP {$statusCode} de {$url}");
        }

        return [
            'success'     => $success,
            'status_code' => $statusCode,
            'response'    => $response,
        ];
    }

    /**
     * Realiza una petición GET al Ministerio y retorna el resultado normalizado.
     * Usada para recuperar un recurso FHIR por su ruta dinámica (ej. /Patient/{uuid}).
     *
     * @param string $endpoint Endpoint relativo. Ej: '/Patient/d30a6eb6-a31b-89e1-e157-58c0bfd196e4'
     * @return array ['success' => bool, 'status_code' => int, 'response' => array]
     */
    private function getMinisterio(string $endpoint): array
    {
        $token      = $this->oauthTokenService->getToken();
        $url        = $this->baseUrl . $endpoint;
        $success    = false;
        $statusCode = 500;
        $response   = [];

        Log::info("[ConsultaMinisterioService] GET → {$url}");

        // Usamos CURL nativo porque el Ministerio devuelve el header
        // 'Cache Control' (con espacio), que Guzzle rechaza como inválido.
        $headers = $this->buildHeaders($token);
        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = "{$name}: {$value}";
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => $curlHeaders,
        ]);

        $body      = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            Log::error("[ConsultaMinisterioService] CURL GET error en {$url}: {$curlError}");
            $response = ['error_interno' => $curlError];
        } else {
            $success = ($statusCode >= 200 && $statusCode < 300);
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                $response = $decoded;
            } else {
                $response = ['raw_body' => $body];
                Log::warning("[ConsultaMinisterioService] Respuesta no-JSON GET (HTTP {$statusCode}): " . substr($body, 0, 300));
            }

            if ($statusCode === 401) {
                Log::warning("[ConsultaMinisterioService] Token IHCE rechazado (401). Limpiando caché.");
                Cache::forget('ihce_oauth_token');
            }

            Log::info("[ConsultaMinisterioService] Respuesta HTTP {$statusCode} de {$url}");
        }

        return [
            'success'     => $success,
            'status_code' => $statusCode,
            'response'    => $response,
        ];
    }

    /**
     * Realiza una petición GET al Ministerio usando una URL **completa** (no relativa).
     *
     * A diferencia de `getMinisterio()`, este método NO concatena `$this->baseUrl`
     * con el parámetro recibido. La URL se consume exactamente como llega.
     *
     * Uso típico: recuperar documentos adjuntos (PDF, imágenes, etc.) cuya URL
     * absoluta ya viene formada en el campo `content[].attachment.url` del recurso FHIR.
     *
     * @param string $fullUrl URL absoluta y completa al recurso del Ministerio.
     * @return array ['success' => bool, 'status_code' => int, 'response' => array]
     */
    private function getMinisterioByFullUrl(string $fullUrl): array
    {
        $token      = $this->oauthTokenService->getToken();
        $success    = false;
        $statusCode = 500;
        $response   = [];

        Log::info("[ConsultaMinisterioService] GET (full-url) → {$fullUrl}");

        // CURL nativo: el Ministerio devuelve 'Cache Control' (con espacio),
        // header no estándar que Guzzle/Http facade rechaza con InvalidArgumentException.
        $headers     = $this->buildHeaders($token);
        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = "{$name}: {$value}";
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => $curlHeaders,
        ]);

        $body       = curl_exec($ch);
        $curlError  = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            Log::error("[ConsultaMinisterioService] CURL error (full-url) en {$fullUrl}: {$curlError}");
            $response = ['error_interno' => $curlError];
        } else {
            $success = ($statusCode >= 200 && $statusCode < 300);
            $decoded = json_decode($body, true);

            if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                $response = $decoded;
            } else {
                // El recurso puede ser binario (PDF/imagen); se retorna en base64
                // para que el cliente pueda procesarlo sin corrupción.
                $response = [
                    'raw_body'    => base64_encode($body),
                    'encoding'    => 'base64',
                    'content_len' => strlen($body),
                ];
                Log::info(
                    "[ConsultaMinisterioService] Respuesta binaria/no-JSON (HTTP {$statusCode}) " .
                    "desde {$fullUrl}. Tamaño: " . strlen($body) . ' bytes.'
                );
            }

            if ($statusCode === 401) {
                Log::warning("[ConsultaMinisterioService] Token IHCE rechazado (401). Limpiando caché.");
                Cache::forget('ihce_oauth_token');
            }

            Log::info("[ConsultaMinisterioService] Respuesta HTTP {$statusCode} de {$fullUrl}");
        }

        return [
            'success'     => $success,
            'status_code' => $statusCode,
            'response'    => $response,
        ];
    }

    /**
     * Consulta un documento externo del Ministerio usando su URL completa (proxy gateway).
     *
     * Actúa como un proxy transparente: recibe la URL formada desde el frontend
     * (obtenida del campo `content[].attachment.url` del FHIR DocumentReference)
     * y la consume directamente sin modificación.
     *
     * @param string $fullUrl URL absoluta del documento en IHCE. Ej:
     *                        https://sandbox.ihcecol.gov.co/ihce/DocumentReference/{uuid}/0
     * @return array
     */
    public function consultarDocumentoExterno(string $fullUrl): array
    {
        Log::info("[ConsultaMinisterioService] Proxy de documento externo → {$fullUrl}");

        return $this->getMinisterioByFullUrl($fullUrl);
    }

    // =========================================================================
    // Endpoints de Consulta al Ministerio
    // =========================================================================

    /**
     * Consulta el Resumen Digital de Atención (RDA) de un paciente por identificador.
     *
     * Endpoint Ministerio: POST /Composition/$consultar-rda-paciente
     *
     * El parámetro `humanuser` es opcional: si se proporciona tipo_doc_usuario y
     * numero_doc_usuario se añade al payload con formato "{tipo}-{numero}".
     *
     * @param string      $tipoDocumento    Código del tipo de doc. Ej: 'CC', 'TI', 'RC'
     * @param string      $numeroDocumento  Número de documento del paciente.
     * @param string|null $tipoDocUsuario   (Opcional) Tipo de doc del usuario que consulta.
     * @param string|null $numeroDocUsuario (Opcional) Número de doc del usuario que consulta.
     * @return array ['success' => bool, 'status_code' => int, 'response' => array]
     */
    public function consultarRdaPaciente(
        string $tipoDocumento,
        string $numeroDocumento,
        ?string $tipoDocUsuario   = null,
        ?string $numeroDocUsuario = null
    ): array {
        Log::info("[ConsultaMinisterioService] Consulta RDA Paciente — Tipo: {$tipoDocumento}, Doc: {$numeroDocumento}");

        $parameters = [
            [
                'name' => 'identifier',
                'part' => [
                    ['name' => 'type',  'valueString' => $tipoDocumento],
                    ['name' => 'value', 'valueString' => $numeroDocumento],
                ],
            ],
        ];

        // Agregar humanuser solo si ambos valores están presentes
        if (!empty($tipoDocUsuario) && !empty($numeroDocUsuario)) {
            $parameters[] = [
                'name'        => 'humanuser',
                'valueString' => "{$tipoDocUsuario}-{$numeroDocUsuario}",
            ];
            Log::info("[ConsultaMinisterioService] humanuser incluido: {$tipoDocUsuario}-{$numeroDocUsuario}");
        }

        $payload = [
            'resourceType' => 'Parameters',
            'parameter'    => $parameters,
        ];

        return $this->postMinisterio('/Composition/$consultar-rda-paciente', $payload);
    }

    /**
     * Consulta los encuentros clínicos de un paciente en el Ministerio IHCE.
     *
     * Endpoint Ministerio: POST /Composition/$consultar-rda-encuentros-clinicos
     *
     * El parámetro `humanuser` es opcional: si se proporciona tipo_doc_usuario y
     * numero_doc_usuario se añade al payload con formato "{tipo}-{numero}".
     *
     * @param string      $tipoDocumento    Tipo de doc del PACIENTE. Ej: 'CC', 'TI', 'RC'
     * @param string      $numeroDocumento  Número de doc del PACIENTE.
     * @param string|null $tipoDocUsuario   (Opcional) Tipo de doc del usuario que consulta.
     * @param string|null $numeroDocUsuario (Opcional) Número de doc del usuario que consulta.
     * @return array ['success' => bool, 'status_code' => int, 'response' => array]
     */
    public function consultarRdaEncuentrosClinicos(
        string $tipoDocumento,
        string $numeroDocumento,
        ?string $tipoDocUsuario   = null,
        ?string $numeroDocUsuario = null
    ): array {
        Log::info(
            "[ConsultaMinisterioService] Consulta Encuentros Clínicos — " .
            "Paciente: {$tipoDocumento}/{$numeroDocumento}"
        );

        $parameters = [
            [
                'name' => 'identifier',
                'part' => [
                    ['name' => 'type',  'valueString' => $tipoDocumento],
                    ['name' => 'value', 'valueString' => $numeroDocumento],
                ],
            ],
        ];

        // Agregar humanuser solo si ambos valores están presentes
        if (!empty($tipoDocUsuario) && !empty($numeroDocUsuario)) {
            $parameters[] = [
                'name'        => 'humanuser',
                'valueString' => "{$tipoDocUsuario}-{$numeroDocUsuario}",
            ];
            Log::info("[ConsultaMinisterioService] humanuser incluido: {$tipoDocUsuario}-{$numeroDocUsuario}");
        }

        $payload = [
            'resourceType' => 'Parameters',
            'parameter'    => $parameters,
        ];

        return $this->postMinisterio('/Composition/$consultar-rda-encuentros-clinicos', $payload);
    }

    /**
     * Consulta los encuentros clínicos de un paciente con filtros de fecha opcionales.
     *
     * Endpoint Ministerio: POST /Composition/$consultar-rda-encuentros-clinicos
     *
     * Soporta los mismos parámetros que consultarRdaEncuentrosClinicos más:
     *   - lastUpdated: rango de fechas de última actualización (start y/o end)
     *   - authoredOn:  rango de fechas de autoría (start y/o end)
     *
     * Todos los filtros de fecha son opcionales. Si no se envía ninguno, se omiten del payload.
     *
     * @param string      $tipoDocumento      Código del tipo de doc. Ej: 'CC', 'TI', 'RC'
     * @param string      $numeroDocumento    Número de documento del paciente.
     * @param string|null $tipoDocUsuario     (Opcional) Tipo de doc del usuario que consulta.
     * @param string|null $numeroDocUsuario   (Opcional) Número de doc del usuario que consulta.
     * @param string|null $lastUpdatedStart   (Opcional) Fecha inicio de lastUpdated. Ej: '2025-08-01'
     * @param string|null $lastUpdatedEnd     (Opcional) Fecha fin de lastUpdated.   Ej: '2025-08-31'
     * @param string|null $authoredOnStart    (Opcional) Fecha inicio de authoredOn.  Ej: '2025-08-01'
     * @param string|null $authoredOnEnd      (Opcional) Fecha fin de authoredOn.    Ej: '2025-08-31'
     * @return array ['success' => bool, 'status_code' => int, 'response' => array]
     */
    public function consultarRdaEncuentrosClinicosFechas(
        string $tipoDocumento,
        string $numeroDocumento,
        ?string $tipoDocUsuario   = null,
        ?string $numeroDocUsuario = null,
        ?string $lastUpdatedStart = null,
        ?string $lastUpdatedEnd   = null,
        ?string $authoredOnStart  = null,
        ?string $authoredOnEnd    = null
    ): array {
        Log::info(
            "[ConsultaMinisterioService] Consulta Encuentros Clínicos con Fechas — " .
            "Paciente: {$tipoDocumento}/{$numeroDocumento}"
        );

        // Parámetro obligatorio: identificador del paciente
        $parameters = [
            [
                'name' => 'identifier',
                'part' => [
                    ['name' => 'type',  'valueString' => $tipoDocumento],
                    ['name' => 'value', 'valueString' => $numeroDocumento],
                ],
            ],
        ];

        // Parámetro opcional: lastUpdated (start y/o end)
        $lastUpdatedParts = [];
        if (!empty($lastUpdatedStart)) {
            $lastUpdatedParts[] = ['name' => 'start', 'valueString' => $lastUpdatedStart];
        }
        if (!empty($lastUpdatedEnd)) {
            $lastUpdatedParts[] = ['name' => 'end', 'valueString' => $lastUpdatedEnd];
        }
        if (!empty($lastUpdatedParts)) {
            $parameters[] = [
                'name' => 'lastUpdated',
                'part' => $lastUpdatedParts,
            ];
            Log::info("[ConsultaMinisterioService] lastUpdated incluido: start={$lastUpdatedStart} end={$lastUpdatedEnd}");
        }

        // Parámetro opcional: authoredOn (start y/o end)
        $authoredOnParts = [];
        if (!empty($authoredOnStart)) {
            $authoredOnParts[] = ['name' => 'start', 'valueString' => $authoredOnStart];
        }
        if (!empty($authoredOnEnd)) {
            $authoredOnParts[] = ['name' => 'end', 'valueString' => $authoredOnEnd];
        }
        if (!empty($authoredOnParts)) {
            $parameters[] = [
                'name' => 'authoredOn',
                'part' => $authoredOnParts,
            ];
            Log::info("[ConsultaMinisterioService] authoredOn incluido: start={$authoredOnStart} end={$authoredOnEnd}");
        }

        // Parámetro opcional: humanuser (auditoría)
        if (!empty($tipoDocUsuario) && !empty($numeroDocUsuario)) {
            $parameters[] = [
                'name'        => 'humanuser',
                'valueString' => "{$tipoDocUsuario}-{$numeroDocUsuario}",
            ];
            Log::info("[ConsultaMinisterioService] humanuser incluido: {$tipoDocUsuario}-{$numeroDocUsuario}");
        }

        $payload = [
            'resourceType' => 'Parameters',
            'parameter'    => $parameters,
        ];

        return $this->postMinisterio('/Composition/$consultar-rda-encuentros-clinicos', $payload);
    }

    // =========================================================================
    // PUNTO DE EXTENSIÓN: agregar aquí los próximos endpoints de consulta
    // =========================================================================

    /*
    |--------------------------------------------------------------------------
    | EJEMPLO — Consultar RDA Urgencias (pendiente de implementar)
    |--------------------------------------------------------------------------
    |
    | public function consultarRdaUrgencias(string $tipoDoc, string $numDoc,
    |                                        string $tipoDocUsuario, string $numDocUsuario): array
    | {
    |     $payload = [ ... payload FHIR específico ... ];
    |     return $this->postMinisterio('/Composition/$consultar-rda-urgencias', $payload);
    | }
    |
    */

    /**
     * Consulta un recurso FHIR por su ruta completa en el Ministerio IHCE.
     *
     * Recibe la ruta FHIR completa y realiza un GET al Ministerio.
     * Ejemplos de $resourcePath:
     *   - /Patient/d30a6eb6-a31b-89e1-e157-58c0bfd196e4
     *   - /Practitioner/10c21843-4267-da2e-6a50-500b51881043
     *   - /Condition/87cd1795-afbe-4992-be3d-695c0fd180cd
     *
     * @param string $resourcePath Ruta FHIR relativa. Ej: '/Patient/d30a6eb6-...'
     * @return array ['success' => bool, 'status_code' => int, 'response' => array]
     */
    public function consultarRecursoPorRuta(string $resourcePath): array
    {
        Log::info("[ConsultaMinisterioService] Consulta recurso por ruta — Path: {$resourcePath}");

        return $this->getMinisterio($resourcePath);
    }

    /**
     * Consulta los datos exactos de un paciente en el Ministerio IHCE.
     *
     * Endpoint Ministerio: POST /Patient/$consultar-paciente-exacto
     *
     * Nota: este endpoint usa la ruta '/Patient/' (no '/Composition/').
     *
     * El parámetro `humanuser` es opcional: si se proporciona tipo_doc_usuario y
     * numero_doc_usuario se añade al payload con formato "{tipo}-{numero}".
     *
     * @param string      $tipoDocumento    Tipo de doc del PACIENTE. Ej: 'CC', 'TI', 'RC'
     * @param string      $numeroDocumento  Número de doc del PACIENTE.
     * @param string|null $tipoDocUsuario   (Opcional) Tipo de doc del usuario que consulta.
     * @param string|null $numeroDocUsuario (Opcional) Número de doc del usuario que consulta.
     * @return array ['success' => bool, 'status_code' => int, 'response' => array]
     */
    public function consultarPacienteExacto(
        string $tipoDocumento,
        string $numeroDocumento,
        ?string $tipoDocUsuario   = null,
        ?string $numeroDocUsuario = null
    ): array {
        Log::info(
            "[ConsultaMinisterioService] Consulta Paciente Exacto — " .
            "Paciente: {$tipoDocumento}/{$numeroDocumento}"
        );

        $parameters = [
            [
                'name' => 'identifier',
                'part' => [
                    ['name' => 'type',  'valueString' => $tipoDocumento],
                    ['name' => 'value', 'valueString' => $numeroDocumento],
                ],
            ],
        ];

        // Agregar humanuser solo si ambos valores están presentes
        if (!empty($tipoDocUsuario) && !empty($numeroDocUsuario)) {
            $parameters[] = [
                'name'        => 'humanuser',
                'valueString' => "{$tipoDocUsuario}-{$numeroDocUsuario}",
            ];
            Log::info("[ConsultaMinisterioService] humanuser incluido: {$tipoDocUsuario}-{$numeroDocUsuario}");
        }

        $payload = [
            'resourceType' => 'Parameters',
            'parameter'    => $parameters,
        ];

        // Nota: este endpoint usa /Patient/ no /Composition/
        return $this->postMinisterio('/Patient/$consultar-paciente-exacto', $payload);
    }

    /**
     * Busca pacientes con datos similares (búsqueda aproximada) en el Ministerio IHCE.
     *
     * Endpoint Ministerio: POST /Patient/$consultar-paciente-similar
     *
     * El parámetro `humanuser` es opcional: si se proporciona tipo_doc_usuario y
     * numero_doc_usuario se añade al payload con formato "{tipo}-{numero}".
     *
     * @param string      $tipoDocumento    Código del tipo de doc. Ej: 'CC', 'TI', 'RC'
     * @param string      $numeroDocumento  Número de documento del paciente.
     * @param string|null $tipoDocUsuario   (Opcional) Tipo de doc del usuario que consulta.
     * @param string|null $numeroDocUsuario (Opcional) Número de doc del usuario que consulta.
     * @return array ['success' => bool, 'status_code' => int, 'response' => array]
     */
    public function consultarPacienteSimilar(
        string $tipoDocumento,
        string $numeroDocumento,
        ?string $tipoDocUsuario   = null,
        ?string $numeroDocUsuario = null
    ): array {
        Log::info(
            "[ConsultaMinisterioService] Consulta Paciente Similar — " .
            "Paciente: {$tipoDocumento}/{$numeroDocumento}"
        );

        $parameters = [
            [
                'name' => 'identifier',
                'part' => [
                    ['name' => 'type',  'valueString' => $tipoDocumento],
                    ['name' => 'value', 'valueString' => $numeroDocumento],
                ],
            ],
        ];

        // Agregar humanuser solo si ambos valores están presentes
        if (!empty($tipoDocUsuario) && !empty($numeroDocUsuario)) {
            $parameters[] = [
                'name'        => 'humanuser',
                'valueString' => "{$tipoDocUsuario}-{$numeroDocUsuario}",
            ];
            Log::info("[ConsultaMinisterioService] humanuser incluido: {$tipoDocUsuario}-{$numeroDocUsuario}");
        }

        $payload = [
            'resourceType' => 'Parameters',
            'parameter'    => $parameters,
        ];

        // Nota: este endpoint usa /Patient/ no /Composition/
        return $this->postMinisterio('/Patient/$consultar-paciente-similar', $payload);
    }
}
