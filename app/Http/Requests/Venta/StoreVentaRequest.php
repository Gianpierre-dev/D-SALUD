<?php

declare(strict_types=1);

namespace App\Http\Requests\Venta;

use Illuminate\Foundation\Http\FormRequest;

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
            'items'                  => ['required', 'array', 'min:1', 'max:50'],
            'items.*.producto_id'    => ['required', 'integer', 'distinct', 'exists:productos,id'],
            'items.*.cantidad'       => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items'               => 'lista de productos',
            'items.*.producto_id' => 'producto',
            'items.*.cantidad'    => 'cantidad',
        ];
    }
}
