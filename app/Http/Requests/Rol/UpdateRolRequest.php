<?php

declare(strict_types=1);

namespace App\Http\Requests\Rol;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolRequest extends FormRequest
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
        $rolId = $this->route('role')->id;

        return [
            'name'          => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($rolId)],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'          => 'nombre del rol',
            'permissions'   => 'permisos',
            'permissions.*' => 'permiso',
        ];
    }
}
