<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class OAuthTokenService
{
    /**
     * Obtiene el token de acceso OAuth. 
     * Implementa persistencia en caché por 55 minutos para reutilizar el token
     * y minimizar las llamadas externas.
     *
     * @return string El access_token válido.
     * @throws Exception Si ocurre un error al obtener el token.
     */
    public function getToken(): string
    {
        // 55 minutos = 3300 segundos
        $cacheTTL = 3300;
        $cacheKey = 'oauth_access_token_ihce';

        // Solo para propósitos de validación y auditoría (ver en storage/logs/laravel.log)
        if (Cache::has($cacheKey)) {
            Log::info("Token IHCE devuelto desde la caché (Sin consumir la API de Microsoft).");
        } else {
            Log::info("Token IHCE no encontrado o expirado. Iniciando petición externa hacia Microsoft...");
        }

        return Cache::remember($cacheKey, $cacheTTL, function () {
            return $this->fetchNewToken();
        });
    }

    /**
     * Realiza la solicitud HTTP para obtener un nuevo token desde el proveedor OAuth.
     *
     * @return string
     * @throws Exception
     */
    private function fetchNewToken(): string
    {
        $config = config('services.oauth_provider');

        $tenantId = $config['tenant_id'];
        $url = "{$config['auth_url']}/{$tenantId}/oauth2/v2.0/token";

        try {
            Log::info("Solicitando nuevo token OAuth a: {$url}");

            $response = Http::asForm()->post($url, [
                'grant_type' => $config['grant_type'],
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'scope' => $config['scope'],
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['access_token'])) {
                    Log::info("Token OAuth obtenido exitosamente y guardado en caché.");
                    return $data['access_token'];
                }

                throw new Exception("La respuesta del servidor no contiene un 'access_token'. Respuesta: " . $response->body());
            }

            throw new Exception("Error al obtener token. Código HTTP: " . $response->status() . " Respuesta: " . $response->body());
        } catch (Exception $e) {
            Log::error('Excepción lanzada en OAuthTokenService::fetchNewToken: ' . $e->getMessage());
            throw new Exception("Fallo en la comunicación con el proveedor OAuth: " . $e->getMessage(), 0, $e);
        }
    }
}
