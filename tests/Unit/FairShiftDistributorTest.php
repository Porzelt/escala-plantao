<?php

namespace Tests\Unit;

use App\Domain\Scheduling\FairShiftDistributor;
use App\Domain\Scheduling\Slot;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FairShiftDistributorTest extends TestCase
{
    /** 30 dias, turno diurno (07h, 12h, peso 1) e noturno (19h, 12h, peso 1.5). */
    private function month(int $days = 30, int $positions = 1): array
    {
        $slots = [];
        for ($d = 1; $d <= $days; $d++) {
            $date = sprintf('2026-11-%02d', $d);
            $slots[] = new Slot($date, 1, '07:00', 12, 1.0, $positions);
            $slots[] = new Slot($date, 2, '19:00', 12, 1.5, $positions);
        }

        return $slots;
    }

    #[Test]
    public function preenche_todas_as_vagas_quando_ha_gente_suficiente(): void
    {
        $result = (new FairShiftDistributor)->distribute($this->month(), range(1, 8));

        $this->assertCount(60, $result->assignments);
        $this->assertSame([], $result->unfilled);
    }

    #[Test]
    public function distribui_carga_de_forma_equilibrada(): void
    {
        $result = (new FairShiftDistributor)->distribute($this->month(), range(1, 8));

        // diferença entre mais e menos carregado não passa do maior peso
        $this->assertLessThanOrEqual(1.5, $result->spread());
        $this->assertEqualsWithDelta(30 * 2.5, array_sum($result->loads), 0.001);
    }

    #[Test]
    public function mesma_semente_gera_a_mesma_escala(): void
    {
        $d = new FairShiftDistributor;

        $a = $d->distribute($this->month(), range(1, 8), seed: 42);
        $b = $d->distribute($this->month(), range(1, 8), seed: 42);
        $c = $d->distribute($this->month(), range(1, 8), seed: 7);

        $this->assertSame($a->assignments, $b->assignments);
        $this->assertNotSame($a->assignments, $c->assignments);
    }

    #[Test]
    public function respeita_indisponibilidade(): void
    {
        $result = (new FairShiftDistributor)->distribute(
            $this->month(),
            range(1, 6),
            unavailable: [3 => ['2026-11-10', '2026-11-11']],
        );

        foreach ($result->assignments as $a) {
            if ($a['user_id'] === 3) {
                $this->assertNotContains($a['date'], ['2026-11-10', '2026-11-11']);
            }
        }
    }

    #[Test]
    public function respeita_descanso_minimo_entre_plantoes(): void
    {
        $result = (new FairShiftDistributor(minRestHours: 12))->distribute($this->month(), range(1, 6));

        $byUser = [];
        foreach ($result->assignments as $a) {
            $start = new \DateTimeImmutable($a['date'].($a['shift_type_id'] === 1 ? ' 07:00' : ' 19:00'));
            $byUser[$a['user_id']][] = $start;
        }

        foreach ($byUser as $starts) {
            sort($starts);
            for ($i = 1; $i < count($starts); $i++) {
                $gap = ($starts[$i]->getTimestamp() - $starts[$i - 1]->getTimestamp()) / 3600;
                // plantão de 12h + 12h de descanso => próximo início pelo menos 24h depois
                $this->assertGreaterThanOrEqual(24, $gap);
            }
        }
    }

    #[Test]
    public function nao_escala_a_mesma_pessoa_duas_vezes_na_mesma_vaga(): void
    {
        $result = (new FairShiftDistributor)->distribute($this->month(5, positions: 3), range(1, 12));

        $keys = array_map(fn ($a) => $a['date'].'|'.$a['shift_type_id'].'|'.$a['user_id'], $result->assignments);
        $this->assertSame(count($keys), count(array_unique($keys)));
        $this->assertCount(5 * 2 * 3, $result->assignments);
    }

    #[Test]
    public function devolve_vagas_nao_preenchidas_sem_violar_regras(): void
    {
        // 1 pessoa não cobre diurno + noturno no mesmo dia com 12h de descanso
        $result = (new FairShiftDistributor)->distribute($this->month(10), [1]);

        $this->assertNotEmpty($result->unfilled);
        $this->assertSame(20, count($result->assignments) + count($result->unfilled));
    }

    #[Test]
    public function compensa_carga_herdada_de_meses_anteriores(): void
    {
        $result = (new FairShiftDistributor)->distribute(
            $this->month(10),
            [1, 2, 3, 4],
            initialLoads: [1 => 10.0],
        );

        $count = array_count_values(array_column($result->assignments, 'user_id'));
        $this->assertLessThan(min($count[2], $count[3], $count[4]), $count[1] ?? 0);
    }

    #[Test]
    public function sem_membros_tudo_fica_em_aberto(): void
    {
        $result = (new FairShiftDistributor)->distribute($this->month(3), []);

        $this->assertSame([], $result->assignments);
        $this->assertCount(6, $result->unfilled);
        $this->assertSame(0.0, $result->spread());
    }
}
