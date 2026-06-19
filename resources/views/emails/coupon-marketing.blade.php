<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cupón disponible</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h1 style="font-size: 22px; margin-bottom: 8px;">Tienes un cupón disponible</h1>

    @if ($customMessage)
        <p>{{ $customMessage }}</p>
    @else
        <p>Usa este cupón en tu próxima compra.</p>
    @endif

    <p style="font-size: 20px; font-weight: bold; letter-spacing: 1px;">{{ $coupon->code }}</p>

    <p>
        Descuento:
        <strong>
            @if ($coupon->discount_type === 'percentage')
                {{ number_format((float) $coupon->discount_value, 2) }}%
            @else
                ${{ number_format((float) $coupon->discount_value, 2) }}
            @endif
        </strong>
    </p>

    @if ($coupon->ends_at)
        <p>Válido hasta {{ $coupon->ends_at->toDateTimeString() }}.</p>
    @endif
</body>
</html>
