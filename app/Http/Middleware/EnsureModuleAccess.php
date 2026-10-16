<?php

namespace App\Http\Middleware;

use App\Enums\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('module:escalas,write')
 */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $module, string $level = 'read'): Response
    {
        $user = $request->user();

        abort_unless($user && $user->active, 403, 'Usuário inativo.');
        abort_unless($user->isAdmin() || $user->canAccessModule(Module::from($module), $level), 403, 'Sem acesso a este módulo.');

        return $next($request);
    }
}
