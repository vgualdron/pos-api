<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notify Hub (SMS / WhatsApp / Email)
    |--------------------------------------------------------------------------
    |
    | API centralizada: https://notify.progresando.co/api/docs
    |
    | Documentación de plantillas y components (body / botón URL):
    | docs/notificaciones-whatsapp.md
    |
    */

    'enabled' => (bool) env('NOTIFY_HUB_ENABLED', false),

    'base_url' => rtrim(env('NOTIFY_HUB_BASE_URL', 'https://notify.progresando.co/api/v1'), '/'),

    'token' => env('NOTIFY_HUB_TOKEN', ''),

    'timeout' => (int) env('NOTIFY_HUB_TIMEOUT', 10),

    /** Canales para turnos. Solo WhatsApp (se ignora sms si aparece en el .env). */
    'channels' => (static function () {
        $raw = array_values(array_filter(array_map(
            'trim',
            explode(',', env('NOTIFY_HUB_CHANNELS', 'whatsapp'))
        )));

        $channels = array_values(array_filter(
            $raw,
            fn (string $channel) => $channel !== '' && strtolower($channel) !== 'sms'
        ));

        return $channels !== [] ? $channels : ['whatsapp'];
    })(),

    /**
     * Enviar recordatorio cuando queden N o menos personas por delante (y al menos 1).
     * Con 0 no se envía: ese momento usa el aviso de "te están llamando".
     * Ejemplo: 2 => avisa con 2 o 1 por delante.
     */
    'reminder_ahead' => (int) env('NOTIFY_HUB_REMINDER_AHEAD', 2),

    /**
     * Nombres de plantillas Meta y forma de variables.
     * El body y el botón URL son espacios distintos en Meta.
     * No se consulta Graph API en cada envío: debe coincidir con lo aprobado en Meta.
     */
    'templates' => [
        'reminder' => [
            'name' => env('NOTIFY_HUB_WA_TEMPLATE_REMINDER', 'turno_recordatorio'),
            'body_count' => 4,
            'url_buttons' => 0,
        ],
        'called' => [
            'name' => env('NOTIFY_HUB_WA_TEMPLATE_CALLED', 'turno_llamado'),
            'body_count' => 3,
            'url_buttons' => 0,
        ],
        'rating' => [
            'name' => env('NOTIFY_HUB_WA_TEMPLATE_RATING', 'turno_calificacion'),
            'body_count' => 2,
            'url_buttons' => 1,
        ],
    ],

    /** Prefijo país si el teléfono llega a 10 dígitos (Colombia). */
    'default_country_code' => env('NOTIFY_HUB_DEFAULT_COUNTRY_CODE', '57'),

    /**
     * URL del front para links de seguimiento / calificación (SMS/WhatsApp).
     * Sin hash: se usa ?track=TOKEN (más seguro en SMS).
     * Ej: https://turno.progresando.co
     *
     * Botón Meta (calificación): https://turno.progresando.co/?track={{1}}
     * Ese {{1}} es del botón, no del body (el body de turno_calificacion usa {{1}} turno y {{2}} servicio).
     */
    'front_url' => rtrim(env('FRONT_URL', env('APP_FRONT_URL', 'https://turno.progresando.co')), '/'),

];
