<?php

namespace App\Services;

use App\Models\Turn;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class TurnNotificationService
{
    public function __construct(protected NotifyHubClient $client)
    {
    }

    public function notifyCalled(Turn $turn): void
    {
        $this->safe(function () use ($turn) {
            $turn->loadMissing(['service', 'place', 'calledByStation']);

            $phone = $this->normalizePhone($turn->patient_phone);
            if (! $phone) {
                return;
            }

            $ticket = (string) $turn->ticket_number;
            $service = (string) ($turn->service?->name ?: 'Servicio');
            $location = $this->formatLocation(
                $turn->calledByStation?->name,
                $turn->place?->name
            );

            $idempotencyKey = sprintf(
                'turno-llamado-%s-%s-%s',
                $ticket,
                $turn->id,
                optional($turn->called_at)->timestamp ?? time()
            );

            $this->client->sendMessage([
                'channels' => config('notify.channels', ['whatsapp']),
                'recipient' => [
                    'phone' => $phone,
                    'name' => $turn->patient_name,
                ],
                'content' => [
                    'body' => sprintf(
                        'Es tu turno %s: te están llamando ahora. Servicio: %s. Lugar: %s.',
                        $ticket,
                        $service,
                        $location
                    ),
                ],
                'whatsapp' => $this->whatsappTemplate('called', [$ticket, $service, $location]),
                'metadata' => [
                    'source' => 'turno-api',
                    'type' => 'turno_llamado',
                    'turn_id' => $turn->id,
                    'ticket_number' => $ticket,
                ],
            ], $idempotencyKey);
        });
    }

    public function notifyReminder(Turn $turn, int $waitingAhead): void
    {
        $this->safe(function () use ($turn, $waitingAhead) {
            $turn->loadMissing(['service', 'place', 'station']);

            $phone = $this->normalizePhone($turn->patient_phone);
            if (! $phone) {
                return;
            }

            $ticket = (string) $turn->ticket_number;
            $service = (string) ($turn->service?->name ?: 'Servicio');
            // Aún no hay consultorio que llama: solo sede. Al llamar sí va "estación - sede".
            $location = $this->formatLocation(null, $turn->place?->name);
            $ahead = (string) $waitingAhead;

            $idempotencyKey = sprintf('turno-recordatorio-%s-%s', $ticket, $turn->id);
            $cacheKey = 'notify:reminder:'.$turn->id;

            // Un solo recordatorio por turno al entrar en la zona "casi te toca"
            if (! Cache::add($cacheKey, 1, now()->addDay())) {
                return;
            }

            $this->client->sendMessage([
                'channels' => config('notify.channels', ['whatsapp']),
                'recipient' => [
                    'phone' => $phone,
                    'name' => $turn->patient_name,
                ],
                'content' => [
                    'body' => sprintf(
                        'Estás muy cerca de llegar a tu turno %s. Servicio: %s. Lugar: %s. Personas por delante: %s. Te avisaremos cuando te llamen.',
                        $ticket,
                        $service,
                        $location,
                        $ahead
                    ),
                ],
                'whatsapp' => $this->whatsappTemplate('reminder', [$ticket, $service, $location, $ahead]),
                'metadata' => [
                    'source' => 'turno-api',
                    'type' => 'turno_recordatorio',
                    'turn_id' => $turn->id,
                    'ticket_number' => $ticket,
                    'waiting_ahead' => $waitingAhead,
                ],
            ], $idempotencyKey);
        });
    }

    /**
     * Tras finalizar la atención, invita a calificar el servicio (SMS / WhatsApp).
     */
    public function notifyRatingInvite(Turn $turn): void
    {
        $this->safe(function () use ($turn) {
            $turn->loadMissing(['service', 'place']);

            $phone = $this->normalizePhone($turn->patient_phone);
            if (! $phone || ! filled($turn->qr_token)) {
                return;
            }

            if ($turn->rated_at || $turn->rating) {
                return;
            }

            $ticket = (string) $turn->ticket_number;
            $service = (string) ($turn->service?->name ?: 'Servicio');
            $qrToken = (string) $turn->qr_token;
            $url = $this->buildTrackUrl($qrToken);
            $cacheKey = 'notify:rating:'.$turn->id;

            if (Cache::has($cacheKey)) {
                return;
            }

            $idempotencyKey = sprintf('turno-calificacion-%s-%s', $ticket, $turn->id);

            // Body Meta: {{1}} turno, {{2}} servicio. El enlace va en el botón URL ({{1}} = qr_token).
            $result = $this->client->sendMessage([
                'channels' => config('notify.channels', ['whatsapp']),
                'recipient' => [
                    'phone' => $phone,
                    'name' => $turn->patient_name,
                ],
                'content' => [
                    'body' => sprintf(
                        'Gracias por su visita. Su turno %s (%s) ha finalizado. Califique nuestro servicio aquí: %s',
                        $ticket,
                        $service,
                        $url
                    ),
                ],
                'whatsapp' => $this->whatsappTemplate(
                    'rating',
                    [$ticket, $service],
                    [$qrToken]
                ),
                'metadata' => [
                    'source' => 'turno-api',
                    'type' => 'turno_calificacion',
                    'turn_id' => $turn->id,
                    'ticket_number' => $ticket,
                ],
            ], $idempotencyKey);

            if ($result !== null) {
                Cache::put($cacheKey, 1, now()->addDay());
            }
        });
    }

    /**
     * Bloque WhatsApp: `components` con body y, si la plantilla lo declara, botón URL.
     *
     * Cómo sabemos si hay botón: no se pregunta a Meta en cada envío.
     * Se declara en config/notify.php → templates.{key}.url_buttons (y body_count).
     * Eso debe copiar lo aprobado en Meta Business (cuerpo vs CTA URL).
     *
     * @param  array<int, string>  $params          Candidatos del body {{1}}, {{2}}, …
     * @param  array<int, string>  $buttonUrlParams Sufijos de botones URL (índice 0, 1, …)
     * @return array<string, mixed>
     */
    public function whatsappTemplate(string $key, array $params, array $buttonUrlParams = []): array
    {
        $layout = $this->templateLayout($key);
        $params = array_values($params);
        $buttonUrlParams = array_values($buttonUrlParams);

        if ($layout['body_count'] !== null) {
            $params = array_slice($params, 0, $layout['body_count']);
        }

        $urlButtonCount = (int) $layout['url_buttons'];
        if ($urlButtonCount < 1) {
            $buttonUrlParams = [];
        } else {
            $buttonUrlParams = array_slice($buttonUrlParams, 0, $urlButtonCount);
        }

        $bodyParameters = [];
        foreach ($params as $value) {
            $bodyParameters[] = [
                'type' => 'text',
                'text' => (string) $value,
            ];
        }

        $components = [];
        if ($bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParameters,
            ];
        }

        foreach ($buttonUrlParams as $index => $value) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => (string) $index,
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => (string) $value,
                    ],
                ],
            ];
        }

        return [
            'template_name' => $layout['name'],
            'params' => $params,
            'components' => $components,
        ];
    }

    /**
     * @return array{name: string, body_count: int|null, url_buttons: int}
     */
    protected function templateLayout(string $key): array
    {
        $raw = config("notify.templates.{$key}", $key);

        if (is_string($raw)) {
            return [
                'name' => $raw,
                'body_count' => null,
                'url_buttons' => 0,
            ];
        }

        if (! is_array($raw)) {
            return [
                'name' => $key,
                'body_count' => null,
                'url_buttons' => 0,
            ];
        }

        $name = (string) ($raw['name'] ?? $key);
        $bodyCount = array_key_exists('body_count', $raw) ? (int) $raw['body_count'] : null;
        $urlButtons = (int) ($raw['url_buttons'] ?? 0);

        return [
            'name' => $name !== '' ? $name : $key,
            'body_count' => $bodyCount !== null && $bodyCount >= 0 ? $bodyCount : null,
            'url_buttons' => max(0, $urlButtons),
        ];
    }

    public function buildTrackUrl(string $qrToken): string
    {
        $token = trim($qrToken);
        $base = (string) config('notify.front_url', 'https://turno.progresando.co');
        // Quitar fragmento (#...) — en SMS el # corta el enlace
        $base = preg_replace('/#.*$/', '', $base) ?: $base;
        $base = rtrim($base, '/');

        return $base.'/?track='.rawurlencode($token);
    }

    /**
     * Tras un cambio en la cola, avisa a quien quedó con N o menos por delante (pero > 0).
     * Con 0 no se envía: ese momento usa el SMS/WhatsApp de "te están llamando".
     */
    public function notifyRemindersForQueue(int $placeId, int $serviceId): void
    {
        $this->safe(function () use ($placeId, $serviceId) {
            if (! $this->client->enabled()) {
                return;
            }

            $threshold = (int) config('notify.reminder_ahead', 2);
            if ($threshold < 1) {
                return;
            }

            $waiting = Turn::with(['service', 'place', 'station'])
                ->where('place_id', $placeId)
                ->where('service_id', $serviceId)
                ->where('status', Turn::STATUS_WAITING)
                ->whereDate('created_at', now()->toDateString())
                ->orderByRaw(Turn::priorityQueueFieldSql())
                ->orderBy('created_at')
                ->get();

            foreach ($waiting as $index => $turn) {
                $ahead = (int) $index;

                // 1..N personas por delante (inclusive). 0 = ya es el siguiente / lo van a llamar.
                if ($ahead >= 1 && $ahead <= $threshold && filled($turn->patient_phone)) {
                    $this->notifyReminder($turn, $ahead);
                }
            }
        });
    }

    /**
     * Lugar = consultorio/estación - sede
     * Ej: "Consultorio Audiometría - Sede Norte"
     */
    public function formatLocation(?string $stationName, ?string $placeName): string
    {
        $station = trim((string) $stationName);
        $place = trim((string) $placeName);

        if ($station !== '' && $place !== '') {
            return $station.' - '.$place;
        }

        if ($station !== '') {
            return $station;
        }

        if ($place !== '') {
            return $place;
        }

        return 'Punto de atención';
    }

    public function normalizePhone(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (! $digits) {
            return null;
        }

        $country = (string) config('notify.default_country_code', '57');

        if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            $digits = $country.$digits;
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    protected function safe(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            Log::error('TurnNotificationService: error', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
