<?php

declare(strict_types=1);

namespace App\Http\Requests\Proveedor;

use Illuminate\Foundation\Http\FormRequest;

class StoreProveedorRequest extends FormRequest
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
        return [
            'ruc'          => ['required', 'string', 'size:11', 'regex:/^[0-9]+$/', 'unique:proveedores,ruc'],
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
