<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;background:#f5f7fb;color:#1f2937;font-family:Arial,Helvetica,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f5f7fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:24px 28px;background:#111827;color:#ffffff;">
                            <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;color:#d1d5db;">
                                {{ $brandName }}
                            </div>
                            <h1 style="margin:10px 0 0;font-size:24px;line-height:1.25;">
                                {{ $title }}
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 18px;font-size:16px;line-height:1.5;">
                                {{ $message }}
                            </p>

                            @if (! empty($details))
                                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;">
                                    @foreach ($details as $label => $value)
                                        <tr>
                                            <td style="padding:11px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-size:13px;color:#6b7280;width:38%;">
                                                {{ $label }}
                                            </td>
                                            <td style="padding:11px 14px;border-bottom:1px solid #e5e7eb;font-size:14px;color:#111827;font-weight:bold;">
                                                {{ $value }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            @if ($actionUrl && $actionLabel)
                                <div style="text-align:center;margin:28px 0 18px;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;background:#2563eb;color:#ffffff;padding:13px 22px;text-decoration:none;border-radius:6px;font-size:15px;font-weight:bold;">
                                        {{ $actionLabel }}
                                    </a>
                                </div>

                                <p style="margin:0;font-size:12px;line-height:1.5;color:#6b7280;text-align:center;">
                                    Si el boton no abre, copia esta liga en tu navegador:<br>{{ $actionUrl }}
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
