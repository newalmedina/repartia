@php
    $euros  = fn($v) => number_format((float) $v, 2, ',', '.') . ' €';
    $center = $payment->center;
    $driver = $payment->driver;
    $modes  = \App\Models\User::paymentModeOptions();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo {{ $payment->number }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 12px; margin: 18px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #d1d5db; }
        .muted { color: #6b7280; }
        .header td { vertical-align: top; }
        .right { text-align: right; }
        .center { text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        .box { border: 1px solid #e5e7eb; padding: 8px 10px; }
        .grid td { padding: 3px 0; }
        .items th { background: #f3f4f6; text-align: left; padding: 5px 6px; font-size: 9px; border-bottom: 1px solid #d1d5db; }
        .items td { padding: 4px 6px; border-bottom: 1px solid #f0f0f0; font-size: 9px; }
        .zero { color: #9ca3af; }
        .totals td { padding: 4px 6px; }
        .totals .grand td { font-size: 13px; font-weight: bold; border-top: 2px solid #1f2937; padding-top: 6px; }
        .sign td { padding-top: 45px; width: 50%; }
        .sign .line { border-top: 1px solid #9ca3af; margin: 0 20px; padding-top: 4px; text-align: center; }
        .footer { position: fixed; bottom: -10px; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>

    {{-- Cabecera: centro y número de recibo --}}
    <table class="header">
        <tr>
            <td style="width: 60%;">
                @if ($center?->image_base64)
                    <img src="{{ $center->image_base64 }}" style="max-height: 55px; max-width: 200px;"><br>
                @endif
                <strong style="font-size: 13px;">{{ $center?->name ?? config('app.name') }}</strong><br>
                @if ($center?->nif) NIF: {{ $center->nif }}<br> @endif
                @if ($center?->full_address) {{ $center->full_address }}<br> @endif
                @if ($center?->phone) Tel.: {{ $center->phone }} @endif
                @if ($center?->email) · {{ $center->email }} @endif
            </td>
            <td class="right" style="width: 40%;">
                <h1>RECIBO DE PAGO</h1>
                <div style="font-size: 13px; margin-top: 4px;"><strong>{{ $payment->number }}</strong></div>
                <div class="muted" style="margin-top: 4px;">
                    Fecha de pago: {{ $payment->paid_at?->format('d/m/Y') ?? '—' }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Repartidor y periodo --}}
    <h2>Repartidor</h2>
    <table class="box grid">
        <tr>
            <td style="width: 50%;"><span class="muted">Nombre:</span> <strong>{{ $driver?->name }}</strong></td>
            <td><span class="muted">DNI/NIE:</span> {{ $driver?->identification ?: '—' }}</td>
        </tr>
        <tr>
            <td><span class="muted">Email:</span> {{ $driver?->email }}</td>
            <td><span class="muted">Teléfono:</span> {{ $driver?->phone ?: '—' }}</td>
        </tr>
        <tr>
            <td><span class="muted">Periodo:</span> {{ $payment->period_from->format('d/m/Y') }} – {{ $payment->period_to->format('d/m/Y') }}</td>
            <td><span class="muted">Forma de pago:</span> {{ $modes[$driver?->payment_mode] ?? '—' }}</td>
        </tr>
    </table>

    {{-- Pedidos --}}
    <h2>Pedidos entregados ({{ $payment->packages->count() }})</h2>
    @if ($payment->packages->isEmpty())
        <p class="muted">Este pago no incluye pedidos.</p>
    @else
        <table class="items">
            <thead>
                <tr>
                    <th>Nº pedido</th>
                    <th>Entregado</th>
                    <th>Destinatario</th>
                    <th>Dirección</th>
                    <th class="right">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payment->packages->sortBy('delivered_at') as $p)
                    <tr>
                        <td>{{ $p->order_number }}</td>
                        <td>{{ $p->delivered_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $p->recipient_name }}</td>
                        <td>
                            {{ $p->address }}, {{ $p->postal_code }} {{ $p->municipality }}
                            @if ($p->same_address) <span class="zero">(misma dirección)</span> @endif
                        </td>
                        <td class="right {{ (float) $p->delivery_price == 0 ? 'zero' : '' }}">{{ $euros($p->delivery_price) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Extras --}}
    @if ($payment->extras->isNotEmpty())
        <h2>Importes extra</h2>
        <table class="items">
            <thead>
                <tr><th>Concepto</th><th class="right">Importe</th></tr>
            </thead>
            <tbody>
                @foreach ($payment->extras as $extra)
                    <tr>
                        <td>{{ $extra->concept }}</td>
                        <td class="right">{{ $euros($extra->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Totales --}}
    <table class="totals" style="width: 45%; margin: 16px 0 0 auto;">
        <tr>
            <td>Comisiones ({{ $payment->packages_count }} pedidos)</td>
            <td class="right">{{ $euros($payment->packages_amount) }}</td>
        </tr>
        <tr>
            <td>Extras</td>
            <td class="right">{{ $euros($payment->extras_amount) }}</td>
        </tr>
        <tr class="grand">
            <td>TOTAL</td>
            <td class="right">{{ $euros($payment->total) }}</td>
        </tr>
    </table>

    @if ($payment->notes)
        <h2>Notas</h2>
        <p>{{ $payment->notes }}</p>
    @endif

    {{-- Firmas --}}
    <table class="sign">
        <tr>
            <td><div class="line">Firma del centro</div></td>
            <td><div class="line">Recibí: {{ $driver?->name }}</div></td>
        </tr>
    </table>

    <div class="footer">
        {{ $center?->name ?? config('app.name') }} · Recibo {{ $payment->number }} · Generado el {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
