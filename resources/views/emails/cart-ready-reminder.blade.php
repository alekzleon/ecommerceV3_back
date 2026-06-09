<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Carrito listo</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h1 style="font-size: 22px; margin-bottom: 4px;">Tu carrito está listo</h1>

    <p>Hola {{ $user->name ?? 'cliente' }}, {{ $createdFromLastPurchase ? 'preparamos un carrito con productos de tu última compra.' : 'tienes un carrito listo para continuar tu compra.' }}</p>

    @if ($cart->items->isNotEmpty())
        <ul>
            @foreach ($cart->items as $item)
                <li>
                    {{ $item->name_snapshot ?? $item->product->name ?? 'Producto' }}
                    -
                    Cantidad: {{ number_format((float) $item->quantity, 2) }}
                </li>
            @endforeach
        </ul>
    @endif

    <p>
        <a href="{{ $cartUrl }}" style="background:#0d6efd;color:#fff;padding:12px 18px;text-decoration:none;border-radius:6px;">
            Ir a mi carrito
        </a>
    </p>

    <p>Gracias.</p>
</body>
</html>
