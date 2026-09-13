<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/css2?family=Carlito&display=swap" rel="stylesheet">
    <style>
        @page {
            margin: 80px 80px 80px 80px;
        }

        body {
            font-family: 'Carlito', sans-serif;
            font-size: 11px;
        }

        .header {
            position: fixed;
            top: -60px;
            left: 60px;
            right: 0px;
            height: 60px;
        }

        .content {
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="header">
        @yield('header')
    </div>

    <div class="content">
        @yield('contenido')
    </div>
</body>
</html>
