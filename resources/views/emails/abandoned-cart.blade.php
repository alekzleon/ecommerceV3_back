<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tu carrito sigue esperándote</title>
</head>
<body style="margin:0;background:#f5f7fb;color:#1f2937;font-family:Arial,Helvetica,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f5f7fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:640px;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:24px 28px;background:#111827;color:#ffffff;">
                            <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;color:#d1d5db;">
                                {{ $settings->site_title ?? 'Cloudi Shop' }}
                            </div>
                            <h1 style="margin:10px 0 0;font-size:24px;line-height:1.25;">
                                Tu carrito sigue listo para ti
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 14px;font-size:16px;line-height:1.5;">
                                Hola {{ $user->name ?? 'cliente' }}, vimos que dejaste algunos productos pendientes.
                            </p>
                            <p style="margin:0 0 22px;font-size:15px;line-height:1.5;color:#4b5563;">
                                Guardamos tu selección para que puedas continuar tu compra sin volver a buscar todo.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                                @foreach ($cart->items as $item)
                                    <tr>
                                        <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;">
                                            <div style="font-size:15px;font-weight:bold;color:#111827;">
                                                {{ $item->name_snapshot ?? $item->product->name ?? 'Producto' }}
                                            </div>
                                            <div style="font-size:13px;color:#6b7280;margin-top:4px;">
                                                Cantidad: {{ (float) $item->quantity }}
                                            </div>
                                        </td>
                                        <td align="right" style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-size:14px;color:#374151;white-space:nowrap;">
                                            ${{ number_format((float) $item->line_subtotal_snapshot, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td style="padding:16px;font-size:15px;font-weight:bold;color:#111827;">
                                        Total estimado
                                    </td>
                                    <td align="right" style="padding:16px;font-size:18px;font-weight:bold;color:#111827;white-space:nowrap;">
                                        ${{ number_format((float) $cart->total_snapshot, 2) }}
                                    </td>
                                </tr>
                            </table>

                            <div style="text-align:center;margin:28px 0 18px;">
                                <a href="{{ $recoverUrl }}" style="display:inline-block;background:#2563eb;color:#ffffff;padding:13px 22px;text-decoration:none;border-radius:6px;font-size:15px;font-weight:bold;">
                                    Recuperar carrito
                                </a>
                            </div>

                            <p style="margin:0;font-size:12px;line-height:1.5;color:#6b7280;text-align:center;">
                                Este enlace es temporal. Si ya completaste tu compra, puedes ignorar este correo.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
