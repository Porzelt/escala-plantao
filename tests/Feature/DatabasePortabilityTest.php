<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Comportamentos que diferem entre MariaDB e PostgreSQL e que a aplicação precisa manter iguais.
 * A suíte roda nos dois bancos no CI (matriz db: mariadb, pgsql) — ver docs/postgres-notes.md.
 */
class DatabasePortabilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function busca_de_membros_ignora_maiusculas_nos_dois_bancos(): void
    {
        $gestor = User::factory()->gestor()->create();
        User::factory()->create(['unit_id' => $gestor->unit_id, 'name' => 'Fulana de Tal']);

        foreach (['fulana', 'FULANA', 'de tal'] as $term) {
            $this->actingAs($gestor)->get('/membros?q='.urlencode($term))
                ->assertInertia(fn ($page) => $page->has('members.data', 1)->where('members.data.0.name', 'Fulana de Tal'));
        }
    }

    #[Test]
    public function mes_duplicado_vira_erro_de_validacao_e_nao_erro_500(): void
    {
        $gestor = User::factory()->gestor()->create();
        Schedule::factory()->create(['unit_id' => $gestor->unit_id, 'month' => '2026-12-01']);

        $this->actingAs($gestor)->post('/escalas', ['month' => '2026-12'])->assertSessionHasErrors('month');
        $this->assertSame(1, Schedule::withoutGlobalScopes()->count());
    }

    #[Test]
    public function painel_agrupa_por_dia_da_semana_sem_funcao_especifica_de_banco(): void
    {
        $this->travelTo('2026-11-10'); // terça
        $gestor = User::factory()->gestor()->create();
        $schedule = Schedule::factory()->create(['unit_id' => $gestor->unit_id, 'month' => '2026-11-01']);
        $type = ShiftType::factory()->create(['unit_id' => $gestor->unit_id]);

        // 2 domingos (01 e 08/11) e 1 sábado (07/11)
        foreach (['2026-11-01', '2026-11-08', '2026-11-07'] as $date) {
            ShiftAssignment::factory()->create(['schedule_id' => $schedule->id, 'shift_type_id' => $type->id, 'user_id' => $gestor->id, 'date' => $date]);
        }

        $this->actingAs($gestor)->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('stats.by_weekday.1', 2)   // domingo
            ->where('stats.by_weekday.7', 1)); // sábado
    }

    #[Test]
    public function auditoria_guarda_e_le_json_igual_nos_dois_bancos(): void
    {
        $gestor = User::factory()->gestor()->create();
        $member = User::factory()->create(['unit_id' => $gestor->unit_id, 'name' => 'Ação Ñandú']);

        $this->actingAs($gestor)->patch("/membros/{$member->id}", ['active' => false]);

        $log = AuditLog::where('auditable_id', $member->id)->where('event', 'created')->firstOrFail();
        $this->assertSame('Ação Ñandú', $log->new_values['name']); // UTF-8 preservado no JSON
    }
}
