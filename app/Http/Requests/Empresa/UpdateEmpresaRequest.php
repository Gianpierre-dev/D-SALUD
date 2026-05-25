<?php

namespace App\Http\Requests\Empresa;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmpresaRequest extends FormRequest
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
            'razon_social' => ['required', 'string', 'max:255'],
            'ruc'          => ['required', 'string', 'size:11', 'regex:/^[0-9]+$/'],
            'direccion'    => ['nullable', 'string', 'max:255'],
            'telefono'     => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'razon_social' => 'razón social',
            'ruc'          => 'RUC',
            'direccion'    => 'dirección',
            'telefono'     => 'teléfono',
        ];
    }
}
