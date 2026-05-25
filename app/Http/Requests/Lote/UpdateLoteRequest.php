<?php

namespace App\Http\Requests\Lote;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoteRequest extends FormRequest
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
        $loteId = $this->route('lote')->id;

        return [
            'producto_id'       => ['required', 'integer', 'exists:productos,id'],
            'proveedor_id'      => ['nullable', 'integer', 'exists:proveedores,id'],
            'codigo_lote'       => [
                'required',
                'string',
                'max:100',
                Rule::unique('lotes', 'codigo_lote')
                    ->where(fn ($query) => $query->where('producto_id', $this->input('producto_id')))
                    ->ignore($loteId),
            ],
            'fecha_vencimiento' => ['required', 'date', 'after:today'],
            'stock'             => ['required', 'integer', 'min:0'],
            'precio_compra'     => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'producto_id'       => 'producto',
            'proveedor_id'      => 'proveedor',
            'codigo_lote'       => 'código de lote',
            'fecha_vencimiento' => 'fecha de vencimiento',
            'stock'             => 'stock',
            'precio_compra'     => 'precio de compra',
        ];
    }
}
