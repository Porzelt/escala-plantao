<?php

namespace App\Http\Middleware;

use App\Enums\Module;
use Illuminate\Http\Request;
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
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user()?->only(['id', 'name', 'email', 'role', 'unit_id']),
                'unit' => fn () => $request->user()?->unit?->only(['id', 'name', 'code']),
                // matriz de módulos do usuário: o menu e os botões do Vue leem daqui
                'can' => fn () => $request->user() ? collect(Module::cases())->mapWithKeys(fn (Module $m) => [
                    $m->value => [
                        'read' => $request->user()->canAccessModule($m),
                        'write' => $request->user()->canAccessModule($m, 'write'),
                    ],
                ])->put('horizon', $request->user()->isAdmin()) : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}
