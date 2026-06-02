<?php

declare(strict_types=1);

namespace App\Http\Requests\Caja;

use App\Enums\TipoMovimientoCaja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovimientoCajaRequest extends FormRequest
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
            'tipo'     => ['required', 'string', Rule::in(TipoMovimientoCaja::values())],
            'monto'    => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'concepto' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo'     => 'tipo de movimiento',
            'monto'    => 'monto',
            'concepto' => 'concepto',
        ];
    }
}
