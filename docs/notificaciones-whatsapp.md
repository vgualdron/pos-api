# Notificaciones de turnos (Notify Hub + WhatsApp Meta)

Documentación del envío WhatsApp desde `turno-api` vía [Notify Hub](https://notify.progresando.co/api/docs).

## Configuración (`.env`)

```env
NOTIFY_HUB_ENABLED=true
NOTIFY_HUB_BASE_URL=https://notify.progresando.co/api/v1
NOTIFY_HUB_TOKEN=...
NOTIFY_HUB_CHANNELS=whatsapp
NOTIFY_HUB_REMINDER_AHEAD=2
NOTIFY_HUB_DEFAULT_COUNTRY_CODE=57

NOTIFY_HUB_WA_TEMPLATE_CALLED=turno_llamado
NOTIFY_HUB_WA_TEMPLATE_REMINDER=turno_recordatorio
NOTIFY_HUB_WA_TEMPLATE_RATING=turno_calificacion

# Front (WhatsApp). El link usa ?track=TOKEN
FRONT_URL=https://turno.progresando.co
```

Ver también `config/notify.php`.

## Cuándo se envía

| Evento | Tipo metadata | Canales | Plantilla WA |
|--------|---------------|---------|--------------|
| Llamado / rellamado | `turno_llamado` | whatsapp | `turno_llamado` |
| Quedan 1..N por delante | `turno_recordatorio` | whatsapp | `turno_recordatorio` |
| Atención finalizada | `turno_calificacion` | whatsapp | `turno_calificacion` |

Código: `App\Services\TurnNotificationService`.

## Estructura WhatsApp (`whatsappTemplate`)

Siempre se envía la **misma forma**: `components` con al menos el `body`.  
Si hay botón URL dinámico, se agrega un componente `button`.

### Sin botón (llamado / recordatorio)

```json
{
  "whatsapp": {
    "template_name": "turno_llamado",
    "params": ["GEP-003", "GENERAL", "Consultorio - Sede"],
    "components": [
      {
        "type": "body",
        "parameters": [
          { "type": "text", "text": "GEP-003" },
          { "type": "text", "text": "GENERAL" },
          { "type": "text", "text": "Consultorio - Sede" }
        ]
      }
    ]
  }
}
```

### Con botón URL (calificación)

```json
{
  "whatsapp": {
    "template_name": "turno_calificacion",
    "params": ["GEP-003", "GENERAL"],
    "components": [
      {
        "type": "body",
        "parameters": [
          { "type": "text", "text": "GEP-003" },
          { "type": "text", "text": "GENERAL" },
          { "type": "text", "text": "eba116fa-..." }
        ]
      },
      {
        "type": "button",
        "sub_type": "url",
        "index": "0",
        "parameters": [
          { "type": "text", "text": "eba116fa-..." }
        ]
      }
    ]
  }
}
```

> **Importante:** si solo se manda el botón en `components` y el body va en `params`, Meta puede recibir **0** variables de body (`#132000`). Por eso el body siempre va dentro de `components`.

## Variables Meta: body vs botón

Son **dos espacios de variables distintos**.

### Body (texto del mensaje)

Orden propio del texto:

| Variable | Valor que envía turno-api |
|----------|---------------------------|
| `{{1}}` | Número de turno |
| `{{2}}` | Nombre del servicio |

### Botón “Ir al sitio web” (dinámico)

El parámetro del botón **no es** el `{{3}}` del body.  
En Meta el botón usa su propio `{{1}}`:

```text
https://turno.progresando.co/?track={{1}}
```

Ese `{{1}}` del botón recibe el mismo UUID (`qr_token`).

| Incorrecto en Meta | Correcto en Meta |
|--------------------|------------------|
| `...?track={{3}}` | `...?track={{1}}` |

Muestra del botón en Meta:

```text
https://turno.progresando.co/?track=13daefaa-afe5-4c80-aa6f-99422db4aacc
```

## Plantillas esperadas

### `turno_llamado`

- Body: turno, servicio, lugar (estación - sede).
- Sin botón URL.

### `turno_recordatorio`

- Body: turno, servicio, lugar, personas por delante.
- Sin botón URL.

### `turno_calificacion`

- Body: `{{1}}` turno, `{{2}}` servicio.
- Botón CTA: texto p. ej. “Calificar servicio”.
- URL dinámica: `https://turno.progresando.co/?track={{1}}` (el `{{1}}` es del botón = `qr_token`).

SMS (texto libre en `content.body`) incluye el link completo:

```text
https://turno.progresando.co/?track=<qr_token>
```

## Errores Meta frecuentes

| Código | Significado | Qué revisar |
|--------|-------------|-------------|
| `#131008` | Falta parámetro del botón URL | Enviar `components` con `button` + `sub_type: url` |
| `#132000` body 3 vs 2 | El body en Meta tiene 2 vars y se enviaron 3 | `turno_calificacion`: body solo turno + servicio; el UUID va en el **botón** |
| `#132000` body params (0 vs n) | Body no llegó a Meta | Incluir `type: body` dentro de `components` (no solo `params`) |

## Link de seguimiento en el front

El SMS/WhatsApp usa `/?track=TOKEN` (sin `#`).  
El front redirige a `#/track/TOKEN` al cargar.

Implementación: `TurnNotificationService::buildTrackUrl()` y router del front (`/?track=` → `#/track/`).
