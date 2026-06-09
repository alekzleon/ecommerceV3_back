<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nuevo mensaje de contacto</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h1 style="font-size: 20px;">Nuevo mensaje de contacto</h1>

    <p>
        <strong>Nombre:</strong> {{ $contact['name'] }}<br>
        <strong>Correo:</strong> {{ $contact['email'] }}
    </p>

    <p><strong>Mensaje:</strong></p>
    <p style="white-space: pre-line;">{{ $contact['message'] }}</p>
</body>
</html>
