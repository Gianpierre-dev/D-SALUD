<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            // Si el correo está cambiando, el usuario debe re-autenticarse con
            // su contraseña actual. Esto cierra el vector de account takeover
            // por sesión secuestrada (atacante apunta el email a uno propio
            // y dispara forgot-password contra la víctima).
            'current_password' => [
                Rule::requiredIf(fn (): bool => $this->input('email') !== $user->email),
                'nullable',
                'current_password',
            ],
        ];
    }
}
