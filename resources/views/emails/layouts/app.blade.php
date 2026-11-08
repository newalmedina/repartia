@php
    // El centro sale del usuario destinatario (llega como $user) o del usuario logueado
    $center = ($user ?? auth()->user())?->center;

    $brandName    = $center?->name ?? config('app.name', 'Repartia');
    $brandLogo    = $center?->image_base64;
    $primaryColor = $center?->primary_color ?? '#2563eb';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $brandName)</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family: Arial, sans-serif;">
    <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:20px 10px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#ffffff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td align="center" style="background-color:{{ $primaryColor }}; padding:20px;">
                            @if ($brandLogo)
                                <img src="{{ $brandLogo }}" alt="{{ $brandName }}" style="max-height:60px; max-width:200px;">
                            @else
                                <span style="color:#ffffff; font-size:22px; font-weight:bold;">{{ $brandName }}</span>
                            @endif
                        </td>
                    </tr>

                    @yield('content')

                    <tr>
                        <td align="center" style="padding:20px; color:#9ca3af; font-size:12px; border-top:1px solid #e5e7eb;">
                            © {{ date('Y') }} {{ $brandName }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
