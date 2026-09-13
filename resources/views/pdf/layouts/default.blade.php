<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/css2?family=Carlito&display=swap" rel="stylesheet">
    <style>
        @page {
            margin: 0 !important;
            padding: 0 !important;
        }

        body {
            font-family: 'Carlito', sans-serif;
            font-size: 11px !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            width: 400px;
            opacity: 0.06;
            z-index: -1;
            transform: translate(-50%, -50%);
        }

        .content {
            margin-top: 40px;
            margin-bottom: 40px;
            padding: 0px 60px 0px 40px;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
        }
    </style>
</head>
<body>

    <!-- <img class="watermark" src="{{ public_path('images/certificates/watermark.png') }}"> -->

    <!-- @include('pdf.components.header') -->

    <div class="content">
        @yield('contenido')
    </div>

    <!-- @include('pdf.components.sign')

    <div class="footer">
        @include('pdf.components.footer')
    </div> -->

</body>
</html>
