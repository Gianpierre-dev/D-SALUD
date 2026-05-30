<?php

declare(strict_types=1);

namespace App\Http\Requests\Usuario;

use App\Enums\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class StoreUsuarioRequest extends FormRequest
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
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'rol'      => ['required', 'string', Rule::in($this->rolesAsignables())],
        ];
    }

    /**
     * Lista blanca de roles que el usuario actual está autorizado a asignar.
     * Solo el Administrador puede asignar el rol Administrador. Esto previene
     * la escalada de privilegios desde cualquier rol con permiso usuarios.create.
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
