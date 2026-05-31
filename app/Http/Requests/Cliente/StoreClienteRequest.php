<?php

declare(strict_types=1);

namespace App\Http\Requests\Cliente;

use App\Enums\TipoDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
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
        $tipo = $this->input('tipo_documento');

        // El número se valida con regex distinta según el tipo:
        //  - DNI: 8 dígitos puros
        //  - RUC: 11 dígitos con prefijo válido (10/15/16/17/20)
        $reglaNumero = $tipo === TipoDocumento::RUC->value
            ? ['required', 'string', 'regex:/^(10|15|16|17|20)\d{9}$/', 'unique:clientes,numero_documento']
            : ['required', 'string', 'regex:/^\d{8}$/', 'unique:clientes,numero_documento'];

        return [
            'tipo_documento'   => ['required', 'string', Rule::in(TipoDocumento::values())],
            'numero_documento' => $reglaNumero,
            'nombre'           => ['required', 'string', 'max:255'],
            'telefono'         => ['nullable', 'string', 'max:20'],
            'email'            => ['nullable', 'email', 'max:255'],
            'direccion'        => ['nullable', 'string', 'max:255'],
            'activo'           => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_documento.regex' => 'El :attribute no cumple el formato exigido para el tipo seleccionado.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo_documento'   => 'tipo de documento',
            'numero_documento' => 'número de documento',
            'nombre'           => 'nombre',
            'telefono'         => 'teléfono',
            'email'            => 'correo',
            'direccion'        => 'dirección',
            'activo'           => 'estado',
        ];
    }
}
