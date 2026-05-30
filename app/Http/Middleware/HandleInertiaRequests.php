<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Resources\AuthUserResource;
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
                // AuthUserResource garantiza que nunca se exponga password,
                // remember_token ni fechas internas del modelo al frontend.
                // El payload completo (id/name/email/roles/permissions) se
                // cachea como unidad para evitar queries Spatie en cada navegación.
                'user' => $user
                    ? Cache::remember(
                        "user.{$user->id}.payload",
                        now()->addMinutes(10),
                        fn () => (new AuthUserResource($user))->resolve(),
                    )
                    : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
