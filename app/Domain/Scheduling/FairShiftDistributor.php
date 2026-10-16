<?php

namespace App\Domain\Scheduling;

use DateTimeImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Distribuição justa de plantões (guloso por menor carga ponderada).
 *
 * Regras:
 *  1. Vagas são preenchidas em ordem cronológica.
 *  2. Cada vaga vai para o membro elegível com MENOR carga acumulada
 *     (carga = soma dos pesos; noturno/fim de semana pesam mais).
 *  3. Empates são decididos por sorteio com semente — mesma semente, mesma escala.
 *  4. Elegível = sem indisponibilidade na data, sem sobreposição e com
 *     descanso mínimo desde o fim do último plantão.
 *  5. Vagas sem candidato elegível são devolvidas em `unfilled` (nunca viola regra).
 *
 * Classe pura (sem Eloquent): fácil de testar e de reaproveitar.
 */
final class FairShiftDistributor
{
    private const EPSILON = 1e-9;

    public function __construct(
        private readonly int $minRestHours = 12,
    ) {}

    /**
     * @param  list<Slot>  $slots
     * @param  list<int>  $memberIds
     * @param  array<int, list<string>>  $unavailable  user_id => datas Y-m-d
     * @param  array<int, float>  $initialLoads  carga herdada (ex.: meses anteriores)
     */
    public function distribute(array $slots, array $memberIds, array $unavailable = [], array $initialLoads = [], int $seed = 1): DistributionResult
    {
        $randomizer = new Randomizer(new Mt19937($seed));

        $loads = [];
        foreach ($memberIds as $id) {
            $loads[$id] = (float) ($initialLoads[$id] ?? 0);
        }

        $blocked = array_map(fn (array $dates) => array_flip($dates), $unavailable);

        /** @var array<int, list<array{0: DateTimeImmutable, 1: DateTimeImmutable}>> $busy */
        $busy = array_fill_keys($memberIds, []);

        usort($slots, fn (Slot $a, Slot $b) => [$a->start, $a->shiftTypeId] <=> [$b->start, $b->shiftTypeId]);

        $assignments = [];
        $unfilled = [];

        foreach ($slots as $slot) {
            $taken = [];

            for ($p = 0; $p < $slot->positions; $p++) {
                $candidates = array_values(array_filter(
                    $memberIds,
                    fn (int $id) => ! isset($taken[$id])
                        && ! isset($blocked[$id][$slot->date])
                        && $this->isRested($busy[$id], $slot),
                ));

                if ($candidates === []) {
                    $unfilled[] = ['date' => $slot->date, 'shift_type_id' => $slot->shiftTypeId];

                    continue;
                }

                $min = min(array_map(fn (int $id) => $loads[$id], $candidates));
                $tied = array_values(array_filter($candidates, fn (int $id) => $loads[$id] - $min < self::EPSILON));
                $chosen = $tied[$randomizer->getInt(0, count($tied) - 1)];

                $taken[$chosen] = true;
                $loads[$chosen] += $slot->weight;
                $busy[$chosen][] = [$slot->start, $slot->end];
                $assignments[] = ['date' => $slot->date, 'shift_type_id' => $slot->shiftTypeId, 'user_id' => $chosen];
            }
        }

        return new DistributionResult($assignments, $unfilled, $loads);
    }

    /**
     * @param  list<array{0: DateTimeImmutable, 1: DateTimeImmutable}>  $periods
     */
    private function isRested(array $periods, Slot $slot): bool
    {
        foreach ($periods as [$start, $end]) {
            $restAfter = $end->modify("+{$this->minRestHours} hours");
            $restBefore = $start->modify("-{$this->minRestHours} hours");

            // conflito se a nova vaga cair dentro de [início - descanso, fim + descanso]
            if ($slot->start < $restAfter && $slot->end > $restBefore) {
                return false;
            }
        }

        return true;
    }
}
