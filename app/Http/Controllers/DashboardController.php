<?php

namespace App\Http\Controllers;

use App\Enums\SwapStatus;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\SwapRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel gerencial. Os agregados ficam em cache Redis por 60s por unidade:
 * é a rota mais acessada e a que o teste de carga k6 exercita.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $scope = $user->isAdmin() ? 'all' : $user->unit_id;

        $stats = Cache::remember("dashboard:{$scope}", 60, function () use ($user) {
            $current = Schedule::whereDate('month', now()->startOfMonth())->pluck('id');

            $perMember = ShiftAssignment::whereIn('schedule_id', $current)
                ->selectRaw('user_id, count(*) as total')->groupBy('user_id')->pluck('total');

            return [
                'members' => User::visibleTo($user)->where('active', true)->count(),
                'shifts_month' => ShiftAssignment::whereIn('schedule_id', $current)->count(),
                'pending_swaps' => SwapRequest::whereIn('status', [SwapStatus::Pendente, SwapStatus::Aceita])
                    ->when(! $user->isAdmin(), fn ($q) => $q->whereHas('requester', fn ($r) => $r->where('unit_id', $user->unit_id)))
                    ->count(),
                'spread' => $perMember->isEmpty() ? 0 : $perMember->max() - $perMember->min(),
                'by_weekday' => ShiftAssignment::whereIn('schedule_id', $current)
                    ->selectRaw('DAYOFWEEK(date) as dow, count(*) as total')->groupBy('dow')->orderBy('dow')
                    ->pluck('total', 'dow'),
            ];
        });

        $next = ShiftAssignment::with('shiftType:id,name,starts_at')
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', today())
            ->orderBy('date')->limit(5)->get()
            ->map(fn ($a) => ['date' => $a->date->locale('pt_BR')->isoFormat('DD/MM (ddd)'), 'shift' => $a->shiftType->name, 'starts_at' => substr($a->shiftType->starts_at, 0, 5)]);

        return Inertia::render('Dashboard', ['stats' => $stats, 'nextShifts' => $next]);
    }
}
