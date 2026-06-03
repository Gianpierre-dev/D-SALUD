<?php

declare(strict_types=1);

namespace App\Http\Requests\Venta;

use App\Enums\MedioPago;
use App\Enums\TipoComprobante;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización fina se aplica vía middleware de permiso en la ruta.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // El tope de items por venta blinda contra amplifier DoS: cada item dispara
        // un Producto::find + un lockForUpdate sobre los lotes; sin tope una sola
        // request podía bloquear la tabla `lotes` durante minutos.
        return [
            'cliente_id'             => ['nullable', 'integer', 'exists:clientes,id'],
            'tipo_comprobante'       => ['nullable', 'string', Rule::in(TipoComprobante::values())],
            'items'                  => ['required', 'array', 'min:1', 'max:50'],
            'items.*.producto_id'    => ['required', 'integer', 'distinct', 'exists:productos,id'],
            'items.*.cantidad'       => ['required', 'integer', 'min:1', 'max:10000'],
            // Desglose de pago: al menos un medio. La suma vs total la valida el
            // VentaService (necesita el total real calculado con FEFO).
            'pagos'                  => ['required', 'array', 'min:1', 'max:5'],
            'pagos.*.medio_pago'     => ['required', 'string', Rule::in(MedioPago::values())],
            'pagos.*.monto'          => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            // Efectivo recibido (para vuelto). Opcional: solo aplica si hay efectivo.
            'monto_recibido'         => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cliente_id'          => 'cliente',
            'items'               => 'lista de productos',
            'items.*.producto_id' => 'producto',
            'items.*.cantidad'    => 'cantidad',
            'pagos'               => 'medios de pago',
            'pagos.*.medio_pago'  => 'medio de pago',
            'pagos.*.monto'       => 'monto',
            'monto_recibido'      => 'monto recibido',
        ];
    }
}
