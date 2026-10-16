<?php

namespace App\Http\Controllers;

use App\Enums\SwapStatus;
use App\Models\ShiftAssignment;
use App\Models\SwapRequest;
use App\Models\User;
use App\Services\SwapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SwapRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = SwapRequest::with(['assignment.shiftType:id,name', 'requester:id,name', 'target:id,name'])->latest();

        // gestor vê a unidade; membro vê só as trocas em que participa
        if ($user->isAdmin()) {
            // sem filtro
        } elseif ($user->isGestor()) {
            $query->whereHas('requester', fn ($q) => $q->where('unit_id', $user->unit_id));
        } else {
            $query->where(fn ($q) => $q->where('requester_id', $user->id)->orWhere('target_user_id', $user->id));
        }

        $myShifts = ShiftAssignment::with(['shiftType:id,name', 'schedule'])
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', today())
            ->whereHas('schedule', fn ($q) => $q->where('status', 'publicada'))
            ->orderBy('date')->limit(30)->get();

        return Inertia::render('Swaps/Index', [
            'swaps' => $query->paginate(20)->through(fn (SwapRequest $s) => [
                'id' => $s->id,
                'date' => $s->assignment->date->toDateString(),
                'shift' => $s->assignment->shiftType->name,
                'requester' => $s->requester->name,
                'target' => $s->target->name,
                'status' => $s->status->value,
                'reason' => $s->reason,
                'can' => [
                    'respond' => $user->can('respond', $s),
                    'decide' => $user->can('decide', $s),
                    'cancel' => $user->can('cancel', $s),
                ],
            ]),
            'myShifts' => $myShifts->map(fn ($a) => ['id' => $a->id, 'label' => $a->date->format('d/m').' — '.$a->shiftType->name]),
            'colleagues' => User::where('unit_id', $user->unit_id)->where('active', true)->whereKeyNot($user->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, SwapService $service): RedirectResponse
    {
        $data = $request->validate([
            'shift_assignment_id' => ['required', 'exists:shift_assignments,id'],
            'target_user_id' => ['required', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $assignment = ShiftAssignment::with('schedule')->findOrFail($data['shift_assignment_id']);
        Gate::authorize('create', [SwapRequest::class, $assignment]);

        $service->request($assignment, $request->user(), User::findOrFail($data['target_user_id']), $data['reason'] ?? null);

        return back()->with('success', 'Troca solicitada. Aguardando o colega aceitar.');
    }

    public function respond(Request $request, SwapRequest $swap, SwapService $service): RedirectResponse
    {
        Gate::authorize('respond', $swap);
        $service->respond($swap, $request->boolean('accept'));

        return back()->with('success', $swap->status === SwapStatus::Aceita ? 'Troca aceita. Falta aprovação do gestor.' : 'Troca recusada.');
    }

    public function decide(Request $request, SwapRequest $swap, SwapService $service): RedirectResponse
    {
        Gate::authorize('decide', $swap);
        $service->decide($swap, $request->user(), $request->boolean('approve'));

        return back()->with('success', 'Decisão registrada.');
    }

    public function cancel(SwapRequest $swap): RedirectResponse
    {
        Gate::authorize('cancel', $swap);
        $swap->update(['status' => SwapStatus::Cancelada]);

        return back()->with('success', 'Troca cancelada.');
    }
}
