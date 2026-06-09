<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recordatorio de compra</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h1 style="font-size: 22px; margin-bottom: 4px;">Hola {{ $user->name ?? 'cliente' }}</h1>

    <p>Han pasado algunos días desde tu última compra. Puedes volver a surtir tus productos cuando lo necesites.</p>

    <p>Última orden: <strong>{{ $order->number }}</strong></p>

    <p>
        <a href="{{ $shopUrl }}" style="background:#0d6efd;color:#fff;padding:12px 18px;text-decoration:none;border-radius:6px;">
            Ver productos
        </a>
    </p>

    <p>Gracias por comprar con nosotros.</p>
</body>
</html>
