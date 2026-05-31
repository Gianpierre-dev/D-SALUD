<?php

declare(strict_types=1);

namespace App\Http\Requests\Compra;

use Illuminate\Foundation\Http\FormRequest;

class AnularCompraRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }
}
