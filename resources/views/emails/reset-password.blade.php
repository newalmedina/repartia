@extends('emails.layouts.app')

@section('title', 'Restablecer contraseña')

@section('content')
<tr>
    <td style="padding: 30px 40px; color: #333333; font-size: 16px; line-height: 1.5;">
        <h2 style="margin-top: 0;">¡Hola {{ $user->name }}!</h2>

        <p>Hemos recibido una solicitud para restablecer tu contraseña. Pulsa el botón para elegir una nueva:</p>

        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 20px 0;">
            <tr>
                <td align="center">
                    <a href="{{ $url }}" style="background-color: #2563eb; color: #ffffff; padding: 12px 25px; text-decoration: none; display: inline-block; font-size: 16px; border-radius: 6px; font-weight: bold;">
                        Restablecer contraseña
                    </a>
                </td>
            </tr>
        </table>

        <p>Este enlace caduca en {{ $count }} minutos.</p>

        <p>Si el botón no funciona, copia y pega esta dirección en tu navegador:</p>

        <p style="word-break: break-all;">
            <a href="{{ $url }}" style="color: #2563eb;">{{ $url }}</a>
        </p>

        <p style="color:#6b7280; font-size:14px;">Si no has pedido este cambio, puedes ignorar este correo.</p>
    </td>
</tr>
@endsection
