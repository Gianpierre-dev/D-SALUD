<?php

declare(strict_types=1);

namespace App\Http\Requests\Usuario;

use App\Enums\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UpdateUsuarioRequest extends FormRequest
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
        $userId = $this->route('user')->id;

        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'lowercase', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            // Misma política NIST que el alta. Solo se valida si se envía un valor.
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'rol'      => ['required', 'string', Rule::in($this->rolesAsignables())],
        ];
    }

    /**
     * Lista blanca de roles que el usuario actual está autorizado a asignar.
     * Solo el Administrador puede asignar el rol Administrador. Esto previene
     * la escalada de privilegios desde cualquier rol con permiso usuarios.update.
     *
     * @return array<int, string>
     */
    private function rolesAsignables(): array
    {
        $actor = $this->user();
        $esAdmin = $actor !== null && $actor->hasRole(Rol::ADMINISTRADOR->value);

        return $esAdmin
            ? Role::query()->pluck('name')->all()
            : Role::query()->where('name', '!=', Rol::ADMINISTRADOR->value)->pluck('name')->all();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'     => 'nombre',
            'email'    => 'correo electrónico',
            'password' => 'contraseña',
            'rol'      => 'rol',
        ];
    }
}
