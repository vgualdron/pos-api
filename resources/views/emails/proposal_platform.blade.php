<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Propuesta Plataforma Biomédica</title>

    <style>
        @page {
            margin: 10mm 10mm 10mm 10mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.5;
        }

        .header {
            border-bottom: 2px solid #e5e5e5;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }

        .logo {
            width: 150px;
        }

        h1 {
            font-size: 15px;
            margin: 20px 0 10px 0;
            color: #1f2937;
        }

        h2 {
            font-size: 12px;
            margin: 18px 0 8px 0;
            color: #1f2937;
        }

        p {
            margin: 6px 0;
        }

        ul {
            margin: 8px 0 8px 18px;
            padding: 0;
        }

        li {
            margin-bottom: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 6px;
            text-align: left;
        }

        th {
            background-color: #f3f4f6;
            font-weight: bold;
        }

        .highlight {
            background-color: #f9fafb;
            padding: 10px;
            border: 1px solid #e5e7eb;
            margin: 12px 0;
        }

        .footer {
            margin-top: 35px;
            font-size: 10px;
            border-top: 1px solid #e5e5e5;
            padding-top: 10px;
        }
    </style>
</head>

<body>

@php
    $equipments = (int) $company->phone;

    if ($equipments <= 12) {
        $plan = 'Micro';
        $monthly = 29900;
    } elseif ($equipments <= 25) {
        $plan = 'Básico';
        $monthly = 59900;
    } elseif ($equipments <= 50) {
        $plan = 'Intermedio';
        $monthly = 99900;
    } elseif ($equipments <= 100) {
        $plan = 'Profesional';
        $monthly = 159900;
    } else {
        $plan = 'Corporativo';
        $monthly = $equipments * 1100;
    }

    $annual = $monthly * 12;
    $annualDiscount = $annual * 0.9;
@endphp

<!-- ENCABEZADO -->
<div class="header">
    <table width="100%" style="border:none;">
        <tr>
            <td style="border:none;">
                <img src="{{ storage_path('/app/public/companies/5/PHOTO_LOGO-1764850948.jpg') }}" width="150">
            </td>
            <td style="border:none; text-align:right;">
                <strong>Propuesta Comercial</strong><br>
                {{ now()->format('d/m/Y') }}
            </td>
        </tr>
    </table>
</div>

<!-- CONTENIDO -->
<h1>Plataforma de Gestión de Mantenimiento Biomédico</h1>

<p>
Estimados <strong>{{ $company->name }}</strong>,
</p>

<p>
Con el objetivo de fortalecer la gestión, trazabilidad y control de los equipos biomédicos,
ponemos a su disposición nuestra plataforma digital especializada,
diseñada para complementar y potenciar el servicio de mantenimiento que actualmente prestamos.
</p>

<h2>Alcance de la plataforma</h2>

<ul>
    <li>Gestión centralizada de inventario biomédico.</li>
    <li>Hoja de vida digital e historial técnico de cada equipo.</li>
    <li>Programación y seguimiento de mantenimientos preventivos y correctivos.</li>
    <li>Reporte de fallas por parte del personal autorizado.</li>
    <li>Generación de informes para auditoría en formatos PDF y Excel.</li>
</ul>

<h2>Plan asignado según su infraestructura</h2>

<div class="highlight">
    <p><strong>Cantidad de equipos:</strong> {{ $equipments }}</p>
    <p><strong>Plan aplicable:</strong> {{ $plan }}</p>
    <p>
        <strong>Inversión mensual:</strong>
        $ {{ number_format($monthly, 0, ',', '.') }} COP
    </p>

    @if($plan === 'Corporativo')
        <p style="font-size:10px;">
            Valor calculado a razón de $1.100 COP por equipo.
        </p>
    @endif
</div>

<h2>Planes disponibles</h2>

<table>
    <tr>
        <th>Plan</th>
        <th>Cantidad de equipos</th>
        <th>Valor mensual (COP)</th>
    </tr>
    <tr>
        <td>Micro</td>
        <td>1 a 12 equipos</td>
        <td>$ 29.900</td>
    </tr>
    <tr>
        <td>Básico</td>
        <td>13 a 25 equipos</td>
        <td>$ 59.900</td>
    </tr>
    <tr>
        <td>Intermedio</td>
        <td>26 a 50 equipos</td>
        <td>$ 99.900</td>
    </tr>
    <tr>
        <td>Profesional</td>
        <td>51 a 100 equipos</td>
        <td>$ 159.900</td>
    </tr>
    <tr>
        <td>Corporativo</td>
        <td>Más de 100 equipos</td>
        <td>$ 1.100 por equipo</td>
    </tr>
</table>

<h2>Opción de pago anual</h2>

<p>
Al optar por el pago anual anticipado,
se otorga un <strong>descuento del 10%</strong> sobre el valor total del periodo.
</p>

<table>
    <tr>
        <th>Concepto</th>
        <th>Valor (COP)</th>
    </tr>
    <tr>
        <td>Valor anual sin descuento</td>
        <td>$ {{ number_format($annual, 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td>Valor anual con 10% de descuento</td>
        <td><strong>$ {{ number_format($annualDiscount, 0, ',', '.') }}</strong></td>
    </tr>
</table>

<p>
La tarifa incluye acceso completo a la plataforma,
soporte técnico y actualizaciones continuas del sistema.
</p>

<p style="font-size:10px; color:#6b7280; margin-top:10px;">
    <strong>Nota:</strong>
    El valor indicado en esta propuesta ha sido calculado con base en la cantidad de equipos registrada actualmente en nuestro sistema.
    Dicho valor se encuentra sujeto a revisión y ajuste en caso de que, durante la validación inicial o la operación del servicio,
    se identifique una cantidad real de equipos diferente.
</p>

<!-- PIE -->
<div class="footer">
    <p>
        JFL Ingeniería y Soluciones<br>
        Propuesta confidencial – Uso exclusivo del destinatario
    </p>
</div>

</body>
</html>
