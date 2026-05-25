<?php

namespace App\Http\Requests\Producto;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductoRequest extends FormRequest
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
        $productoId = $this->route('producto')->id;

        return [
            'codigo'        => ['required', 'string', 'max:50', Rule::unique('productos', 'codigo')->ignore($productoId)],
            'nombre'        => ['required', 'string', 'max:255'],
            'categoria_id'  => ['required', 'integer', 'exists:categorias,id'],
            'laboratorio'   => ['nullable', 'string', 'max:255'],
            'unidad_medida' => ['required', 'string', 'max:50'],
            'precio_venta'  => ['required', 'numeric', 'min:0'],
            'stock_minimo'  => ['required', 'integer', 'min:0'],
            'activo'        => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'codigo'        => 'código',
            'nombre'        => 'nombre',
            'categoria_id'  => 'categoría',
            'laboratorio'   => 'laboratorio',
            'unidad_medida' => 'unidad de medida',
            'precio_venta'  => 'precio de venta',
            'stock_minimo'  => 'stock mínimo',
            'activo'        => 'estado',
        ];
    }
}
