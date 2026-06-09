<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nuevo registro de contacto</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h1 style="font-size: 20px;">Nuevo registro de contacto</h1>

    <p>
        <strong>Nombre:</strong> {{ $lead['name'] }}<br>
        <strong>Correo:</strong> {{ $lead['email'] }}
    </p>
</body>
</html>
