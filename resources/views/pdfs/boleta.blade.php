<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boleta {{ $venta->boleta?->numero_formateado }}</title>
    <style>
        /*
         * DomPDF soporta CSS 2.1 y un subconjunto de CSS 3.
         * Usamos table-layout (no flexbox) para máxima compatibilidad.
         */
        @page {
            margin: 18mm 16mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 14px;
        }
        .header img {
            max-height: 80px;
            margin-bottom: 6px;
        }
        .header h1 {
            font-size: 16px;
            font-weight: bold;
            margin: 0;
            color: #111827;
        }
        .header p {
            margin: 2px 0;
            font-size: 11px;
            color: #4b5563;
        }
        .divider {
            border: 0;
            border-top: 1px dashed #9ca3af;
            margin: 10px 0;
        }
        .titulo-boleta {
            text-align: center;
            margin-bottom: 10px;
        }
        .titulo-boleta h2 {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 0 0 4px 0;
            color: #111827;
        }
        .titulo-boleta .numero {
            font-size: 15px;
            font-weight: bold;
            color: #2563eb;
            margin: 0;
        }
        .meta {
            width: 100%;
            margin-bottom: 10px;
            font-size: 11px;
        }
        .meta td {
            padding: 2px 0;
        }
        .meta .etiqueta {
            color: #6b7280;
            width: 80px;
        }
        table.detalles {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.detalles thead th {
            border-bottom: 1px solid #d1d5db;
            padding: 5px 4px;
            font-weight: bold;
            font-size: 11px;
            color: #374151;
        }
        table.detalles tbody td {
            border-bottom: 1px solid #f3f4f6;
            padding: 5px 4px;
            font-size: 11px;
        }
        .text-right {
            text-align: right;
        }
        .text-left {
            text-align: left;
        }
        .total {
            width: 100%;
            border-top: 1px solid #d1d5db;
            padding-top: 8px;
            margin-top: 6px;
        }
        .total td {
            padding: 4px 0;
        }
        .total .etiqueta {
            font-weight: bold;
            font-size: 13px;
            color: #111827;
        }
        .total .monto {
            text-align: right;
            font-weight: bold;
            font-size: 16px;
            color: #2563eb;
        }
        .anulada {
            margin-top: 14px;
            padding: 10px 12px;
            border: 1px solid #fca5a5;
            background-color: #fef2f2;
            color: #b91c1c;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
        }
        .anulada small {
            display: block;
            font-weight: normal;
            font-size: 10px;
            color: #991b1b;
            margin-top: 3px;
        }
        .pie {
            text-align: center;
            margin-top: 24px;
            font-size: 10px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    @php
        $boleta   = $venta->boleta;
        $vendedor = $venta->vendedor;
        $fecha    = $boleta?->fecha_emision
            ? \Illuminate\Support\Carbon::parse($boleta->fecha_emision)->format('d/m/Y H:i')
            : '—';
    @endphp

    {{-- Cabecera con logo + datos de empresa --}}
    <div class="header">
        @if ($logoPath && file_exists($logoPath))
            <img src="{{ $logoPath }}" alt="{{ $empresa->razon_social ?? "D'Salud" }}">
        @endif
        <h1>{{ $empresa->razon_social ?? "D'Salud S.A.C." }}</h1>
        @if ($empresa->ruc ?? null)
            <p>RUC: {{ $empresa->ruc }}</p>
        @endif
        @if ($empresa->direccion ?? null)
            <p>{{ $empresa->direccion }}</p>
        @endif
        @if ($empresa->telefono ?? null)
            <p>Tel: {{ $empresa->telefono }}</p>
        @endif
    </div>

    <hr class="divider">

    {{-- Título y número de boleta --}}
    <div class="titulo-boleta">
        <h2>Boleta de Venta</h2>
        <p class="numero">{{ $boleta?->numero_formateado ?? '—' }}</p>
    </div>

    {{-- Meta: fecha + vendedor --}}
    <table class="meta">
        <tr>
            <td class="etiqueta">Fecha:</td>
            <td>{{ $fecha }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Vendedor:</td>
            <td>{{ $vendedor?->name ?? '—' }}</td>
        </tr>
    </table>

    <hr class="divider">

    {{-- Tabla de detalles --}}
    <table class="detalles">
        <thead>
            <tr>
                <th class="text-left">Producto</th>
                <th class="text-right">Cant.</th>
                <th class="text-right">P. Unit.</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($venta->detalles as $detalle)
                <tr>
                    <td class="text-left">{{ $detalle->producto?->nombre ?? '—' }}</td>
                    <td class="text-right">{{ $detalle->cantidad }}</td>
                    <td class="text-right">S/ {{ number_format((float) $detalle->precio_unitario, 2) }}</td>
                    <td class="text-right">S/ {{ number_format((float) $detalle->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Total --}}
    <table class="total">
        <tr>
            <td class="etiqueta">TOTAL</td>
            <td class="monto">S/ {{ number_format((float) $venta->total, 2) }}</td>
        </tr>
    </table>

    @if ($venta->estado === \App\Models\Venta::ESTADO_ANULADA)
        <div class="anulada">
            BOLETA ANULADA
            @if ($venta->motivo_anulacion)
                <small>Motivo: {{ $venta->motivo_anulacion }}</small>
            @endif
        </div>
    @endif

    <p class="pie">Gracias por su compra.</p>
</body>
</html>
