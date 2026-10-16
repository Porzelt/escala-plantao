<?php

namespace App\Providers;

use App\Enums\Module;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Um gate por módulo/nível: @can('modulo.escalas.write') no back e `can` no front.
        foreach (Module::cases() as $module) {
            foreach (['read', 'write'] as $level) {
                Gate::define("modulo.{$module->value}.{$level}", fn (User $u) => $u->canAccessModule($module, $level));
            }
        }
    }
}
