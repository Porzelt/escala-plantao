<?php

namespace App\Services;

use App\Domain\Scheduling\DistributionResult;
use App\Domain\Scheduling\FairShiftDistributor;
use App\Domain\Scheduling\Slot;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use App\Models\Unavailability;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Ponte entre o banco e o algoritmo puro: monta as vagas do mês,
 * chama o distribuidor e grava o resultado numa transação.
 */
class ScheduleGenerator
{
    public function __construct(private readonly FairShiftDistributor $distributor) {}

    public function generate(Schedule $schedule, ?int $seed = null): DistributionResult
    {
        $seed ??= random_int(1, 999_999);
        $month = $schedule->month->copy()->startOfMonth();

        $shiftTypes = ShiftType::withoutGlobalScopes()->where('unit_id', $schedule->unit_id)->get();
        $memberIds = User::where('unit_id', $schedule->unit_id)->where('active', true)->orderBy('id')->pluck('id')->all();

        $slots = [];
        foreach (CarbonPeriod::create($month, $month->copy()->endOfMonth()) as $day) {
            foreach ($shiftTypes as $type) {
                // fim de semana vale 1.5x a mais — regra de exemplo de "justiça"
                $weight = $type->weight * ($day->isWeekend() ? 1.5 : 1);
                $slots[] = new Slot($day->toDateString(), $type->id, substr($type->starts_at, 0, 5), $type->duration_hours, $weight, $type->slots);
            }
        }

        $unavailable = Unavailability::whereIn('user_id', $memberIds)
            ->whereBetween('date', [$month, $month->copy()->endOfMonth()])
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => $r->date->toDateString())->all())
            ->all();

        $result = $this->distributor->distribute($slots, $memberIds, $unavailable, $this->previousLoads($schedule, $memberIds), $seed);

        DB::transaction(function () use ($schedule, $result, $seed) {
            $schedule->assignments()->delete();

            $now = now();
            $rows = array_map(fn ($a) => $a + ['schedule_id' => $schedule->id, 'created_at' => $now, 'updated_at' => $now], $result->assignments);
            foreach (array_chunk($rows, 500) as $chunk) {
                ShiftAssignment::insert($chunk);
            }

            $schedule->update(['seed' => $seed]);
        });

        return $result;
    }

    /** Quantidade de plantões do mês anterior: quem trabalhou mais começa "devendo menos". */
    private function previousLoads(Schedule $schedule, array $memberIds): array
    {
        $previous = Schedule::withoutGlobalScopes()
            ->where('unit_id', $schedule->unit_id)
            ->whereDate('month', $schedule->month->copy()->subMonthNoOverflow()->startOfMonth())
            ->first();

        if (! $previous) {
            return [];
        }

        $counts = $previous->assignments()->whereIn('user_id', $memberIds)
            ->selectRaw('user_id, count(*) as total')->groupBy('user_id')->pluck('total', 'user_id');

        if ($counts->isEmpty()) {
            return [];
        }

        // normaliza pelo mínimo para não acumular indefinidamente
        $min = $counts->min();

        return $counts->map(fn ($c) => (float) ($c - $min))->all();
    }
}
