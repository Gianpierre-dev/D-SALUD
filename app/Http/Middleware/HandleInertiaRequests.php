<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    // Roles y permisos se cachean por usuario con TTL corto: evita
                    // resolver 3-4 queries spatie en cada navegación de Inertia.
                    'roles' => Cache::remember(
                        "user.{$user->id}.roles",
                        now()->addMinutes(10),
                        fn () => $user->getRoleNames(),
                    ),
                    'permissions' => Cache::remember(
                        "user.{$user->id}.permissions",
                        now()->addMinutes(10),
                        fn () => $user->getAllPermissions()->pluck('name'),
                    ),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
