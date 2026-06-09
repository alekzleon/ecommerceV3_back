<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Acceso a tu cuenta</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h1 style="font-size: 20px;">Bienvenido a Abarrotes Raúl</h1>

    <p>Hola {{ $user->name }},</p>

    <p>Hemos creado tu usuario para ingresar al portal.</p>

    <p>
        <strong>Usuario:</strong> {{ $user->email }}<br>
        <strong>Contraseña temporal:</strong> {{ $temporaryPassword }}
    </p>

    <p>
        Por seguridad, al iniciar sesión deberás cambiar esta contraseña.
    </p>

    <p>
        <a href="{{ $loginUrl }}" style="display: inline-block; background: #111827; color: #ffffff; padding: 10px 16px; text-decoration: none; border-radius: 6px;">
            Iniciar sesión
        </a>
    </p>

    <p>Si el botón no abre, copia esta liga en tu navegador:<br>{{ $loginUrl }}</p>
</body>
</html>
