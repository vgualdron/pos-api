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

        $clientId = self::cleanCredential($settings->client_id);
        $username = self::cleanCredential($settings->username);
        $clientSecret = self::cleanCredential($settings->client_secret);
        $password = (string) ($settings->password ?? '');

        if ($settings->hasClientSecret() && $clientSecret === '') {
            throw new Exception(
                'El client secret está guardado pero no se pudo leer (cifrado inválido). Vuelva a pegarlo y guarde.'
            );
        }
        if ($settings->hasPassword() && $password === '') {
            throw new Exception(
                'La contraseña está guardada pero no se pudo leer (cifrado inválido). Vuelva a pegarla y guarde.'
            );
        }

        $baseUrl = $settings->baseUrl();
        $payload = [
            'grant_type' => 'password',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'username' => $username,
            'password' => $password,
        ];

        try {
            // Factus docs ask for form-data; fall back to x-www-form-urlencoded (their Laravel example).
            $response = Http::asMultipart()
                ->acceptJson()
                ->timeout(45)
                ->post($baseUrl . '/oauth/token', $payload);

            if (! $response->successful() && ($response->json('error') ?? null) === 'invalid_client') {
                $response = Http::asForm()
                    ->acceptJson()
                    ->timeout(45)
                    ->post($baseUrl . '/oauth/token', $payload);
            }
        } catch (ConnectionException $e) {
            throw new Exception(
                'No se pudo conectar con Factus en ' . $baseUrl . '. Verifique la URL y que el servidor tenga salida HTTPS. Detalle: ' . $e->getMessage()
            );
        }

        if (! $response->successful()) {
            throw new Exception($this->formatAuthError(
                $response->status(),
                $response->json(),
                $response->body(),
                $baseUrl,
                $clientId,
                $username,
                strlen($clientSecret),
                strlen($password)
            ));
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

    /**
     * List Factus numbering ranges.
     * $document: Factus range document code (21 invoice, 22 credit note, ...) or "all".
     */
    public function getNumberingRanges(CompanyElectronicInvoiceSetting $settings, ?string $document = '21'): array
    {
        $document = $document !== null ? trim($document) : '21';

        if ($document === '' || strtolower($document) === 'all') {
            $merged = [];
            foreach (['21', '22', '23', '24'] as $code) {
                foreach ($this->fetchNumberingRanges($settings, $code) as $row) {
                    $id = $row['id'] ?? null;
                    if ($id === null) {
                        continue;
                    }
                    $merged[(string) $id] = $row;
                }
            }

            return array_values($merged);
        }

        return $this->fetchNumberingRanges($settings, $document);
    }

    protected function fetchNumberingRanges(CompanyElectronicInvoiceSetting $settings, string $document): array
    {
        // Same query string shape as Postman collection
        $query = http_build_query([
            'filter[id]' => '',
            'filter[document]' => $document,
            'filter[resolution_number]' => '',
            'filter[technical_key]' => '',
            'filter[is_active]' => '1',
        ]);

        $url = $settings->baseUrl() . '/v2/numbering-ranges?' . $query;
        $token = $this->authenticate($settings);

        try {
            // Do not use asJson() on GET — mirrors Postman Accept + query string only
            $response = Http::withToken($token)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(45)
                ->get($url);
        } catch (ConnectionException $e) {
            throw new Exception('No se pudo conectar con Factus al listar rangos: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            throw new Exception(
                'Factus rechazó el listado de rangos (HTTP ' . $response->status() . '): '
                . $this->extractError($response->json(), $response->body(), 'Error desconocido')
                . ' [url=' . $url . ']'
            );
        }

        Log::info('Factus numbering-ranges', [
            'url' => $url,
            'status' => $response->status(),
            'count' => count($this->extractDataRows($response->json() ?: [])),
        ]);

        return $this->extractDataRows($response->json() ?: []);
    }

    public function getDianNumberingRanges(CompanyElectronicInvoiceSetting $settings): array
    {
        try {
            $response = $this->authorized($settings)
                ->get($settings->baseUrl() . '/v2/numbering-ranges/dian');
        } catch (ConnectionException $e) {
            throw new Exception('No se pudo consultar rangos DIAN en Factus: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            throw new Exception(
                'Factus rechazó rangos DIAN (HTTP ' . $response->status() . '): '
                . $this->extractError($response->json(), $response->body(), 'Error desconocido')
            );
        }

        return $this->extractDataRows($response->json() ?: []);
    }

    public function createNumberingRange(CompanyElectronicInvoiceSetting $settings, array $data): array
    {
        $document = (string) ($data['document'] ?? '');
        $prefix = trim((string) ($data['prefix'] ?? ''));
        $current = $data['current'] ?? null;

        if ($document === '' || $prefix === '' || $current === null || $current === '') {
            throw new Exception('document, prefix y current son obligatorios.');
        }

        if (strlen($prefix) > 4) {
            throw new Exception('El prefijo puede tener máximo 4 caracteres.');
        }

        $body = [
            'document' => $document,
            'prefix' => $prefix,
            'current' => is_numeric($current) ? (0 + $current) : $current,
        ];

        // Invoice and support docs require DIAN resolution number
        if (in_array($document, ['21', '24'], true)) {
            $resolution = trim((string) ($data['resolution_number'] ?? ''));
            if ($resolution === '') {
                throw new Exception('resolution_number es obligatorio para factura (21) y documento soporte (24).');
            }
            $body['resolution_number'] = $resolution;
        }

        $response = $this->authorized($settings)
            ->post($settings->baseUrl() . '/v2/numbering-ranges', $body);

        if (! $response->successful()) {
            throw new Exception(
                'No se pudo crear el rango (HTTP ' . $response->status() . '): '
                . $this->extractError($response->json(), $response->body(), 'Error desconocido')
            );
        }

        $payload = $response->json() ?: [];
        if (isset($payload['data']) && is_array($payload['data'])) {
            return $payload['data'];
        }

        return $payload;
    }

    public function updateNumberingRangeCurrent(CompanyElectronicInvoiceSetting $settings, int $rangeId, $current): array
    {
        $response = $this->authorized($settings)
            ->patch($settings->baseUrl() . '/v2/numbering-ranges/' . $rangeId . '/current', [
                'current' => is_numeric($current) ? (0 + $current) : $current,
            ]);

        if (! $response->successful()) {
            throw new Exception(
                'No se pudo actualizar el consecutivo (HTTP ' . $response->status() . '): '
                . $this->extractError($response->json(), $response->body(), 'Error desconocido')
            );
        }

        $payload = $response->json() ?: [];

        return isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
    }

    public function toggleNumberingRangeStatus(CompanyElectronicInvoiceSetting $settings, int $rangeId): array
    {
        $response = $this->authorized($settings)
            ->patch($settings->baseUrl() . '/v2/numbering-ranges/' . $rangeId . '/toggle-status');

        if (! $response->successful()) {
            throw new Exception(
                'No se pudo cambiar el estado del rango (HTTP ' . $response->status() . '): '
                . $this->extractError($response->json(), $response->body(), 'Error desconocido')
            );
        }

        $payload = $response->json() ?: [];

        return isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
    }

    protected function extractDataRows(array $payload): array
    {
        if (isset($payload['data']) && is_array($payload['data'])) {
            return array_values($payload['data']);
        }

        // Some endpoints return a bare list
        if ($payload !== [] && array_keys($payload) === range(0, count($payload) - 1)) {
            return $payload;
        }

        return [];
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


    public static function cleanCredential(?string $value): string
    {
        $value = trim((string) $value);
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = substr($value, -1);
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = trim(substr($value, 1, -1));
            }
        }

        return $value;
    }

    protected function formatAuthError(
        int $status,
        ?array $json,
        string $body,
        string $baseUrl,
        string $clientId,
        string $username,
        int $secretLen,
        int $passwordLen
    ): string {
        $raw = $this->extractError($json, $body, 'No se pudo autenticar con Factus.');
        $hint = '';

        $errorCode = is_array($json) ? ($json['error'] ?? null) : null;
        if ($errorCode === 'invalid_client') {
            $hint = ' Eso significa que Factus no reconoce el par client_id/client_secret para esa URL.'
                . ' Si la URL es sandbox, las credenciales deben ser de sandbox (no de producción), y viceversa.';
        } elseif ($errorCode === 'invalid_grant') {
            $hint = ' Revise usuario (email) y contraseña de Factus.';
        }

        $diag = sprintf(
            ' [URL=%s | client_id=%s | username=%s | secret_len=%d | password_len=%d]',
            $baseUrl,
            $clientId !== '' ? $clientId : '(vacío)',
            $username !== '' ? $username : '(vacío)',
            $secretLen,
            $passwordLen
        );

        Log::warning('Factus auth failed', [
            'status' => $status,
            'base_url' => $baseUrl,
            'client_id' => $clientId,
            'username' => $username,
            'secret_len' => $secretLen,
            'password_len' => $passwordLen,
            'body' => mb_substr($body, 0, 500),
        ]);

        return 'Autenticación Factus falló (HTTP ' . $status . '): ' . $raw . $hint . $diag;
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
