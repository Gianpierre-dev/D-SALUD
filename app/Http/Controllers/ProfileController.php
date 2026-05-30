<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Notifications\EmailChangedNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $emailAnterior = $user->email;

        $user->fill($request->only('name', 'email'));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // Si el correo cambió, notificar al correo anterior para que el titular
        // detecte cualquier intento de takeover y pueda accionar a tiempo.
        if ($emailAnterior !== $user->email) {
            Notification::route('mail', $emailAnterior)
                ->notify(new EmailChangedNotification($emailAnterior, $user->email));
        }

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Bloquear que el único Administrador del sistema elimine su cuenta:
        // dejaría el sistema sin acceso administrativo, sin forma de recuperarlo.
        if ($user->hasRole(Rol::ADMINISTRADOR->value)
            && User::role(Rol::ADMINISTRADOR->value)->count() <= 1) {
            throw ValidationException::withMessages([
                'password' => 'No puedes eliminar la única cuenta de Administrador del sistema.',
            ]);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
