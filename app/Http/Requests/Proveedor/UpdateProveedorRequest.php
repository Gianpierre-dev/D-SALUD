<?php

namespace App\Http\Requests\Proveedor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProveedorRequest extends FormRequest
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
        $proveedorId = $this->route('proveedor')->id;

        return [
            'ruc'          => ['required', 'string', 'size:11', 'regex:/^[0-9]+$/', Rule::unique('proveedores', 'ruc')->ignore($proveedorId)],
            'razon_social' => ['required', 'string', 'max:255'],
            'contacto'     => ['nullable', 'string', 'max:255'],
            'telefono'     => ['nullable', 'string', 'max:20'],
            'email'        => ['nullable', 'email', 'max:255'],
            'direccion'    => ['nullable', 'string', 'max:255'],
            'activo'       => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ruc'          => 'RUC',
            'razon_social' => 'razón social',
            'contacto'     => 'contacto',
            'telefono'     => 'teléfono',
            'email'        => 'correo',
            'direccion'    => 'dirección',
            'activo'       => 'estado',
        ];
    }
}
