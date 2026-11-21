<?php

namespace App\Services\Factus;

use App\Models\CompanyElectronicInvoiceSetting;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FactusClient
{
    public function authenticate(CompanyElectronicInvoiceSetting $settings, bool $force = false): string
    {
        $this->assertCredentials($settings);

        try {
            $cachedToken = $settings->access_token;
        } catch (DecryptException $e) {
            $cachedToken = null;
            $settings->access_token = null;
            $settings->token_expires_at = null;
        }

        if (
            ! $force
            && $cachedToken
            && $settings->token_expires_at
            && $settings->token_expires_at->isFuture()
        ) {
            return $cachedToken;
        }

        try {
            $clientSecret = trim((string) $settings->client_secret);
            $password = (string) $settings->password;
        } catch (DecryptException $e) {
            throw new Exception(
                'No se pudieron descifrar las credenciales guardadas. Vuelva a pegar client secret y contraseña y guarde de nuevo.'
            );
        }

        $baseUrl = $settings->baseUrl();
        $payload = [
            'grant_type' => 'password',
            'client_id' => trim((string) $settings->client_id),
            'client_secret' => $clientSecret,
            'username' => trim((string) $settings->username),
            'password' => $password,
        ];

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(45)
                ->post($baseUrl . '/oauth/token', $payload);
        } catch (ConnectionException $e) {
            throw new Exception(
                'No se pudo conectar con Factus en ' . $baseUrl . '. Verifique la URL y que el servidor tenga salida HTTPS. Detalle: ' . $e->getMessage()
            );
        }

        if (! $response->successful()) {
            throw new Exception($this->formatAuthError($response->status(), $response->json(), $response->body(), $baseUrl));
        }

        $body = $response->json() ?: [];
        $token = $body['access_token'] ?? null;
        if (! $token) {
            throw new Exception('Factus respondió 200 pero sin access_token.');
        }

        $expiresIn = (int) ($body['expires_in'] ?? 3600);
        $settings->access_token = $token;
        $settings->token_expires_at = now()->addSeconds(max(60, $expiresIn - 60));
        $settings->last_error = null;
        $settings->save();

        return $token;
    }

    public function getNumberingRanges(CompanyElectronicInvoiceSetting $settings): array
    {
        try {
            $response = $this->authorized($settings)
                ->get($settings->baseUrl() . '/v2/numbering-ranges', [
                    'filter' => ['is_active' => 1],
                ]);
        } catch (ConnectionException $e) {
            throw new Exception('No se pudo conectar con Factus al listar rangos: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            throw new Exception(
                'Factus rechazó el listado de rangos (HTTP ' . $response->status() . '): '
                . $this->extractError($response->json(), $response->body(), 'Error desconocido')
            );
        }

        $payload = $response->json() ?: [];

        if (isset($payload['data']) && is_array($payload['data'])) {
            return $payload['data'];
        }

        return is_array($payload) ? $payload : [];
    }

    public function postJson(CompanyElectronicInvoiceSetting $settings, string $path, array $body): array
    {
        $url = $settings->baseUrl() . '/' . ltrim($path, '/');
        $response = $this->authorized($settings)->post($url, $body);

        if (! $response->successful()) {
            throw new Exception($this->extractError($response->json(), $response->body(), 'Error al llamar a Factus.'));
        }

        return $response->json() ?: [];
    }

    protected function authorized(CompanyElectronicInvoiceSetting $settings): PendingRequest
    {
        $token = $this->authenticate($settings);

        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(45);
    }

    protected function assertCredentials(CompanyElectronicInvoiceSetting $settings): void
    {
        if (! filled($settings->api_url)) {
            throw new Exception('Debe configurar la URL de la API de Factus.');
        }
        if (! filled($settings->client_id) || ! filled($settings->username)) {
            throw new Exception('Debe configurar client_id y usuario de Factus.');
        }

        try {
            $hasSecret = filled($settings->client_secret);
            $hasPassword = filled($settings->password);
        } catch (DecryptException $e) {
            throw new Exception(
                'No se pudieron descifrar las credenciales guardadas. Vuelva a pegar client secret y contraseña y guarde de nuevo.'
            );
        }

        if (! $hasSecret || ! $hasPassword) {
            throw new Exception(
                'Faltan client secret y/o contraseña. Pégalos otra vez y guarda (no se muestran al reabrir el modal).'
            );
        }
    }


    protected function formatAuthError(int $status, ?array $json, string $body, string $baseUrl): string
    {
        $raw = $this->extractError($json, $body, 'No se pudo autenticar con Factus.');
        $hint = '';

        $errorCode = is_array($json) ? ($json['error'] ?? null) : null;
        if ($errorCode === 'invalid_client') {
            $hint = ' Revise client_id / client_secret y que correspondan al mismo ambiente que la URL (' . $baseUrl . ').';
        } elseif ($errorCode === 'invalid_grant') {
            $hint = ' Revise usuario (email) y contraseña de Factus.';
        }

        Log::warning('Factus auth failed', [
            'status' => $status,
            'base_url' => $baseUrl,
            'body' => mb_substr($body, 0, 500),
        ]);

        return 'Autenticación Factus falló (HTTP ' . $status . '): ' . $raw . $hint;
    }

    protected function extractError(?array $json, string $body, string $fallback): string
    {
        if (is_array($json)) {
            foreach (['error_description', 'message', 'error'] as $key) {
                if (! empty($json[$key]) && is_string($json[$key])) {
                    return $json[$key];
                }
            }

            if (! empty($json['errors']) && is_array($json['errors'])) {
                $flat = collect($json['errors'])->flatten()->filter()->implode(' ');
                if ($flat !== '') {
                    return $flat;
                }
            }
        }

        $trimmed = trim($body);

        return $trimmed !== '' ? mb_substr($trimmed, 0, 500) : $fallback;
    }
}
