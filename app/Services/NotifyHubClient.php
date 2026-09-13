<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyHubClient
{
    public function enabled(): bool
    {
        return (bool) config('notify.enabled')
            && filled(config('notify.token'))
            && filled(config('notify.base_url'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function sendMessage(array $payload, ?string $idempotencyKey = null): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.config('notify.token'),
        ];

        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        try {
            $response = Http::baseUrl((string) config('notify.base_url'))
                ->withHeaders($headers)
                ->timeout((int) config('notify.timeout', 10))
                ->post('/messages', $payload);

            if (! $response->successful()) {
                Log::warning('Notify Hub: envío fallido', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                    'idempotency_key' => $idempotencyKey,
                ]);

                return null;
            }

            return $response->json();
        } catch (Throwable $e) {
            Log::error('Notify Hub: excepción al enviar', [
                'message' => $e->getMessage(),
                'idempotency_key' => $idempotencyKey,
            ]);

            return null;
        }
    }
}
