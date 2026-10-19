<?php

namespace App\Http\Controllers;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ShiftType;
use App\Models\Unit;
use App\Services\ScheduleGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        $schedules = Schedule::with('unit:id,name,code')
            ->withCount('assignments')
            ->orderByDesc('month')
            ->paginate(12);

        return Inertia::render('Schedules/Index', [
            'schedules' => $schedules,
            'units' => $request->user()->isAdmin() ? Unit::orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Schedule::class);

        $user = $request->user();
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'unit_id' => [Rule::requiredIf($user->isAdmin()), 'nullable', 'exists:units,id'],
        ]);

        $unitId = $user->isAdmin() ? (int) $data['unit_id'] : $user->unit_id;
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth()->toDateString();

        // Checagem explícita com a data já normalizada (Y-m-01). A regra `unique` compararia o texto
        // "2026-12" com a coluna date: o MariaDB converte em silêncio e nunca acha duplicata, o Postgres rejeita.
        $exists = Schedule::withoutGlobalScopes()->where('unit_id', $unitId)->whereDate('month', $month)->exists();
        if ($exists) {
            throw ValidationException::withMessages(['month' => 'Já existe escala para este mês nesta unidade.']);
        }

        $schedule = Schedule::create([
            'unit_id' => $unitId,
            'month' => $month,
            'status' => ScheduleStatus::Rascunho,
            'created_by' => $user->id,
        ]);

        return to_route('schedules.show', $schedule)->with('success', 'Escala criada. Gere a distribuição.');
    }

    public function show(Schedule $schedule): Response
    {
        Gate::authorize('view', $schedule);

        $schedule->load(['unit:id,name,code', 'assignments.user:id,name', 'assignments.shiftType:id,name']);

        $loads = $schedule->assignments->groupBy('user_id')->map(fn ($rows) => [
            'name' => $rows->first()->user->name,
            'total' => $rows->count(),
        ])->sortBy('name')->values();

        return Inertia::render('Schedules/Show', [
            'schedule' => $schedule->only(['id', 'month', 'status', 'seed', 'unit']),
            'shiftTypes' => ShiftType::withoutGlobalScopes()->where('unit_id', $schedule->unit_id)->get(['id', 'name', 'starts_at', 'duration_hours']),
            'assignments' => $schedule->assignments->map(fn ($a) => [
                'id' => $a->id,
                'date' => $a->date->toDateString(),
                'shift_type_id' => $a->shift_type_id,
                'user_id' => $a->user_id,
                'user' => $a->user->name,
            ]),
            'loads' => $loads,
            'can' => [
                'update' => Gate::allows('update', $schedule),
                'publish' => Gate::allows('publish', $schedule),
            ],
        ]);
    }

    public function generate(Request $request, Schedule $schedule, ScheduleGenerator $generator): RedirectResponse
    {
        Gate::authorize('update', $schedule);

        $seed = $request->validate(['seed' => ['nullable', 'integer', 'min:1', 'max:999999']])['seed'] ?? null;
        $result = $generator->generate($schedule, $seed);

        $msg = sprintf('%d plantões distribuídos (diferença de carga: %.1f).', count($result->assignments), $result->spread());
        if ($result->unfilled) {
            $msg .= sprintf(' %d vagas sem candidato elegível.', count($result->unfilled));
        }

        return back()->with('success', $msg);
    }

    public function publish(Schedule $schedule): RedirectResponse
    {
        Gate::authorize('publish', $schedule);

        $schedule->update(['status' => ScheduleStatus::Publicada, 'published_at' => now()]);

        return back()->with('success', 'Escala publicada. Trocas liberadas para a equipe.');
    }
}
