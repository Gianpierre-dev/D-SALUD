<?php

declare(strict_types=1);

namespace App\Http\Requests\Compra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'proveedor_id'                  => ['required', 'integer', 'exists:proveedores,id'],
            'fecha_compra'                  => ['required', 'date_format:Y-m-d'],
            'observaciones'                 => ['nullable', 'string', 'max:500'],
            'items'                         => ['required', 'array', 'min:1', 'max:50'],
            'items.*.producto_id'           => [
                'required',
                'integer',
                Rule::exists('productos', 'id')->where(fn ($q) => $q->where('activo', true)),
            ],
            'items.*.cantidad'              => ['required', 'integer', 'min:1', 'max:99999'],
            'items.*.precio_unitario'       => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'items.*.codigo_lote'           => ['required', 'string', 'max:100'],
            'items.*.fecha_vencimiento'     => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }
}
