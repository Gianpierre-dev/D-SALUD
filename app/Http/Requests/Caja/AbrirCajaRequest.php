<?php

declare(strict_types=1);

namespace App\Http\Requests\Caja;

use Illuminate\Foundation\Http\FormRequest;

class AbrirCajaRequest extends FormRequest
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
            'monto_apertura' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'observaciones'  => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'monto_apertura' => 'monto de apertura',
            'observaciones'  => 'observaciones',
        ];
    }
}
