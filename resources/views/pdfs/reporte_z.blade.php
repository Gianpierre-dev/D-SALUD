<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Z — Caja #{{ $caja->id }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        body  { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        .header { text-align: center; margin-bottom: 14px; }
        .header img { max-height: 70px; margin-bottom: 6px; }
        .header h1 { margin: 0; font-size: 16px; color: #111827; }
        .header p  { margin: 2px 0; font-size: 11px; color: #4b5563; }
        .divider { border: 0; border-top: 1px dashed #9ca3af; margin: 10px 0; }
        .titulo  { text-align: center; margin-bottom: 12px; }
        .titulo h2 {
            font-size: 14px; font-weight: bold; letter-spacing: 2px;
            text-transform: uppercase; margin: 0 0 4px 0; color: #111827;
        }
        .titulo .numero { font-size: 14px; font-weight: bold; color: #2563eb; margin: 0; }
        table.bloque { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        table.bloque th {
            text-align: left; padding: 4px 0; font-size: 11px;
            color: #6b7280; font-weight: normal; width: 45%;
        }
        table.bloque td { padding: 4px 0; font-size: 11px; }
        .seccion {
            font-weight: bold; text-transform: uppercase;
            font-size: 11px; color: #374151;
            border-bottom: 1px solid #d1d5db; padding-bottom: 3px; margin: 12px 0 6px 0;
        }
        .total-fila {
            border-top: 2px solid #111827; padding-top: 6px; margin-top: 6px;
        }
        .total-fila td { font-size: 14px; font-weight: bold; }
        .diferencia-ok       { color: #047857; }
        .diferencia-sobrante { color: #047857; }
        .diferencia-faltante { color: #b91c1c; }
        .pie { text-align: center; margin-top: 26px; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
    @php
        $diferencia = (float) $caja->diferencia;
        $estadoDif  = $diferencia === 0.0
            ? ['clase' => 'diferencia-ok',       'texto' => 'CUADRADA']
            : ($diferencia > 0
                ? ['clase' => 'diferencia-sobrante', 'texto' => 'SOBRANTE']
                : ['clase' => 'diferencia-faltante', 'texto' => 'FALTANTE']);
    @endphp

    <div class="header">
        @if ($logoPath && file_exists($logoPath))
            <img src="{{ $logoPath }}" alt="{{ $empresa->razon_social ?? "D'Salud" }}">
        @endif
        <h1>{{ $empresa->razon_social ?? "D'Salud S.A.C." }}</h1>
        @if ($empresa->ruc ?? null)<p>RUC: {{ $empresa->ruc }}</p>@endif
        @if ($empresa->direccion ?? null)<p>{{ $empresa->direccion }}</p>@endif
    </div>

    <hr class="divider">

    <div class="titulo">
        <h2>Reporte Z — Cuadre de Caja</h2>
        <p class="numero">CAJA #{{ str_pad((string) $caja->id, 5, '0', STR_PAD_LEFT) }}</p>
    </div>

    <p class="seccion">Apertura</p>
    <table class="bloque">
        <tr>
            <th>Cajero</th>
            <td>{{ $caja->cajero?->name ?? '—' }}</td>
        </tr>
        <tr>
            <th>Fecha y hora</th>
            <td>{{ $caja->abierta_en?->format('d/m/Y H:i:s') }}</td>
        </tr>
        <tr>
            <th>Monto de apertura</th>
            <td>S/ {{ number_format((float) $caja->monto_apertura, 2) }}</td>
        </tr>
    </table>

    <p class="seccion">Movimiento del turno</p>
    <table class="bloque">
        <tr>
            <th>Total ventas COMPLETADAS</th>
            <td>S/ {{ number_format((float) $caja->total_ventas, 2) }}</td>
        </tr>
        <tr>
            <th>Total esperado en caja (apertura + ventas)</th>
            <td>S/ {{ number_format((float) $caja->total_esperado, 2) }}</td>
        </tr>
    </table>

    <p class="seccion">Cierre</p>
    <table class="bloque">
        <tr>
            <th>Cerrada por</th>
            <td>{{ $caja->cerradaPor?->name ?? '—' }}</td>
        </tr>
        <tr>
            <th>Fecha y hora</th>
            <td>{{ $caja->cerrada_en?->format('d/m/Y H:i:s') }}</td>
        </tr>
        <tr>
            <th>Monto contado físicamente</th>
            <td>S/ {{ number_format((float) $caja->monto_cierre, 2) }}</td>
        </tr>
        <tr class="total-fila">
            <td>Diferencia ({{ $estadoDif['texto'] }})</td>
            <td class="{{ $estadoDif['clase'] }}">
                S/ {{ number_format($diferencia, 2) }}
            </td>
        </tr>
    </table>

    @if ($caja->observaciones)
        <p class="seccion">Observaciones</p>
        <p>{{ $caja->observaciones }}</p>
    @endif

    <p class="pie">
        Documento generado automáticamente el {{ now()->format('d/m/Y H:i:s') }}.
        Este reporte es válido como sello de cierre interno del turno.
    </p>
</body>
</html>
