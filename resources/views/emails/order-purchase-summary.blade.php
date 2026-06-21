<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Detalle de compra</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h1 style="font-size: 22px; margin-bottom: 4px;">Gracias por tu compra</h1>
    <p style="margin-top: 0;">Orden <strong>{{ $order->number }}</strong></p>
    @if ($order->orden_compra)
        <p style="margin-top: 0;">Orden de compra <strong>{{ $order->orden_compra }}</strong></p>
    @endif

    @if (!empty($isAdminNotification))
        <p>Se registró una nueva compra en Cloudi Shop.</p>
    @else
        <p>Hola {{ $user->name ?? 'cliente' }}, recibimos tu compra correctamente.</p>
    @endif

    @if ($order->document_notes)
        <div style="margin-top: 14px; padding: 12px; border: 1px solid #e5e7eb; background: #f9fafb;">
            <strong>Notas del documento</strong>
            <p style="margin: 6px 0 0;">{{ $order->document_notes }}</p>
        </div>
    @endif

    @php
        $coupon = data_get($order->metadata, 'coupon');
        $couponDiscount = (float) data_get($coupon, 'discount_amount', 0);
    @endphp

    <table style="border-collapse: collapse; width: 100%; margin-top: 16px;">
        <thead>
            <tr>
                <th style="text-align: left; border-bottom: 1px solid #e5e7eb; padding: 8px;">Producto</th>
                <th style="text-align: right; border-bottom: 1px solid #e5e7eb; padding: 8px;">Cantidad</th>
                <th style="text-align: right; border-bottom: 1px solid #e5e7eb; padding: 8px;">Precio</th>
                <th style="text-align: right; border-bottom: 1px solid #e5e7eb; padding: 8px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                @php
                    $giftUnits = (float) data_get($item->metadata, 'gift_units', data_get($item->promotion_snapshot, 'gift_units', 0));
                    $giftLineTotal = (float) data_get($item->metadata, 'gift_line_total', data_get($item->promotion_snapshot, 'gift_line_total', 0));
                    $giftUnitPrice = data_get($item->metadata, 'gift_unit_accounting_price', data_get($item->promotion_snapshot, 'gift_unit_accounting_price'));
                    $discountPercentage = data_get($item->promotion_snapshot, 'discount_percentage');
                    $fromQuantity = data_get($item->promotion_snapshot, 'from_quantity');
                    $toQuantity = data_get($item->promotion_snapshot, 'to_quantity');
                    $selectedAttributes = collect(data_get($item->metadata, 'selected_attributes', []))
                        ->map(fn ($selected) => trim((string) data_get($selected, 'attribute')) . ': ' . trim((string) data_get($selected, 'value')))
                        ->filter()
                        ->implode(' / ');
                @endphp
                <tr>
                    <td style="border-bottom: 1px solid #f3f4f6; padding: 8px;">
                        {{ $item->name_snapshot }}
                        @if ($selectedAttributes)
                            <br><span style="color: #6b7280; font-size: 12px;">{{ $selectedAttributes }}</span>
                        @endif
                        @if ($item->promotion_name_snapshot)
                            <br><span style="color: #6b7280; font-size: 12px;">Promo: {{ $item->promotion_name_snapshot }}</span>
                        @endif
                        @if ($discountPercentage && $fromQuantity)
                            <br><span style="color: #6b7280; font-size: 12px;">Escala aplicada: {{ $discountPercentage }}% desde {{ $fromQuantity }}{{ $toQuantity ? ' hasta '.$toQuantity : '+' }} pieza(s)</span>
                        @endif
                        @if ($giftUnits > 0)
                            <br><span style="color: #6b7280; font-size: 12px;">Incluye {{ $giftUnits }} unidad(es) de regalo facturadas a ${{ number_format((float) $giftUnitPrice, 2) }}</span>
                        @endif
                    </td>
                    <td style="border-bottom: 1px solid #f3f4f6; padding: 8px; text-align: right;">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td style="border-bottom: 1px solid #f3f4f6; padding: 8px; text-align: right;">${{ number_format((float) $item->unit_price, 2) }}</td>
                    <td style="border-bottom: 1px solid #f3f4f6; padding: 8px; text-align: right;">${{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
                @if ($giftUnits > 0)
                    <tr>
                        <td colspan="4" style="border-bottom: 1px solid #f3f4f6; padding: 8px; color: #6b7280; font-size: 12px;">
                            Total contable de regalos: ${{ number_format($giftLineTotal, 2) }}.
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 16px; margin-left: auto; border-collapse: collapse;">
        <tr>
            <td style="padding: 4px 12px;">Subtotal</td>
            <td style="padding: 4px 12px; text-align: right;">${{ number_format((float) $order->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 12px;">Descuento</td>
            <td style="padding: 4px 12px; text-align: right;">${{ number_format((float) $order->discount, 2) }}</td>
        </tr>
        @if ($couponDiscount > 0)
            <tr>
                <td style="padding: 4px 12px;">Cupón {{ data_get($coupon, 'code') }}</td>
                <td style="padding: 4px 12px; text-align: right;">-${{ number_format($couponDiscount, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding: 4px 12px;">Impuestos</td>
            <td style="padding: 4px 12px; text-align: right;">${{ number_format((float) $order->tax, 2) }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 12px;"><strong>Total pagado</strong></td>
            <td style="padding: 4px 12px; text-align: right;"><strong>${{ number_format((float) $order->total, 2) }}</strong></td>
        </tr>
    </table>

    <p style="margin-top: 20px;">Puedes revisar tu compra y seguimiento siempre en Cloudi Shop.</p>
</body>
</html>
