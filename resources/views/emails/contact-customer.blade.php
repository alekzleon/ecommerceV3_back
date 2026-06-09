<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibimos tu mensaje</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h1 style="font-size: 20px;">Gracias por contactarnos</h1>

    <p>Hola {{ $contact['name'] }},</p>

    <p>Recibimos tu mensaje correctamente. Nuestro equipo te responderá lo antes posible.</p>

    <p><strong>Tu mensaje:</strong></p>
    <p style="white-space: pre-line;">{{ $contact['message'] }}</p>
</body>
</html>
