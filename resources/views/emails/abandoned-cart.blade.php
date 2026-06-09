<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tu carrito sigue esperándote</title>
</head>
<body style="font-family: Arial, sans-serif; color: #222;">
    <h2>Hola {{ $user->name ?? 'cliente' }}, tu carrito sigue esperándote</h2>

    <p>Notamos que dejaste productos en tu carrito.</p>

    <ul>
        @foreach ($cart->items as $item)
            <li>
                {{ $item->name_snapshot ?? $item->product->name ?? 'Producto' }}
                -
                Cantidad: {{ $item->quantity }}
            </li>
        @endforeach
    </ul>

    <p>
        <a href="{{ $recoverUrl }}" style="background:#0d6efd;color:#fff;padding:12px 18px;text-decoration:none;border-radius:6px;">
            Recuperar carrito
        </a>
    </p>

    <p>Gracias.</p>
</body>
</html>