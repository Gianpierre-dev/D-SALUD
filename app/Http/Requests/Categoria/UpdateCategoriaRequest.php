<?php

namespace App\Http\Requests\Categoria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoriaRequest extends FormRequest
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
        $categoriaId = $this->route('categoria')->id;

        return [
            'nombre' => ['required', 'string', 'max:255', Rule::unique('categorias', 'nombre')->ignore($categoriaId)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'descripcion' => 'descripción',
            'activo' => 'estado',
        ];
    }
}
