<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventario;

use App\Enums\MotivoMovimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMovimientoInventarioRequest extends FormRequest
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
            'lote_id'     => ['required', 'integer', 'exists:lotes,id'],
            // Solo motivos manuales: VENTA / ANULACION_VENTA / COMPRA están reservados
            // a flujos automáticos y no pueden registrarse desde el módulo manual.
            'motivo'      => ['required', 'string', Rule::in(MotivoMovimiento::manualesValues())],
            'cantidad'    => ['required', 'integer', 'min:1', 'max:99999'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lote_id'     => 'lote',
            'motivo'      => 'motivo',
            'cantidad'    => 'cantidad',
            'observacion' => 'observación',
        ];
    }
}
