<?php

namespace App\Services\Factus;

use App\Models\CompanyElectronicInvoiceSetting;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class FactusClient
{
    public function authenticate(CompanyElectronicInvoiceSetting $settings, bool $force = false): string
    {
        $this->assertCredentials($settings);

        if (
            ! $force
            && $settings->access_token
            && $settings->token_expires_at
            && $settings->token_expires_at->isFuture()
        ) {
            return $settings->access_token;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post($settings->baseUrl() . '/oauth/token', [
                'grant_type' => 'password',
                'client_id' => $settings->client_id,
                'client_secret' => $settings->client_secret,
                'username' => $settings->username,
                'password' => $settings->password,
            ]);

        if (! $response->successful()) {
            throw new Exception($this->extractError($response->json(), $response->body(), 'No se pudo autenticar con Factus.'));
        }

        $payload = $response->json() ?: [];
        $token = $payload['access_token'] ?? null;
        if (! $token) {
            throw new Exception('Factus no devolvió access_token.');
        }

        $expiresIn = (int) ($payload['expires_in'] ?? 3600);
        $settings->access_token = $token;
        $settings->token_expires_at = now()->addSeconds(max(60, $expiresIn - 60));
        $settings->last_error = null;
        $settings->save();

        return $token;
    }

    public function getNumberingRanges(CompanyElectronicInvoiceSetting $settings): array
    {
        $response = $this->authorized($settings)->get($settings->baseUrl() . '/v2/numbering-ranges');

        if (! $response->successful()) {
            throw new Exception($this->extractError($response->json(), $response->body(), 'No se pudieron obtener los rangos de numeración.'));
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
        if (! filled($settings->client_id) || ! filled($settings->client_secret)) {
            throw new Exception('Debe configurar client_id y client_secret de Factus.');
        }
        if (! filled($settings->username) || ! filled($settings->password)) {
            throw new Exception('Debe configurar usuario y contraseña de Factus.');
        }
    }

    protected function extractError(?array $json, string $body, string $fallback): string
    {
        if (is_array($json)) {
            foreach (['message', 'error', 'error_description'] as $key) {
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
