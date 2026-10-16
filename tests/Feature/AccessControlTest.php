<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public static function moduleMatrix(): array
    {
        // [perfil, rota, status esperado]
        return [
            'membro não vê membros' => ['membro', '/membros', 403],
            'membro não vê auditoria' => ['membro', '/auditoria', 403],
            'membro vê escalas' => ['membro', '/escalas', 200],
            'membro acessa trocas' => ['membro', '/trocas', 200],
            'gestor vê membros' => ['gestor', '/membros', 200],
            'gestor vê auditoria' => ['gestor', '/auditoria', 200],
            'admin vê auditoria' => ['admin', '/auditoria', 200],
        ];
    }

    #[Test]
    #[DataProvider('moduleMatrix')]
    public function matriz_de_modulos_por_perfil(string $role, string $uri, int $status): void
    {
        $user = match ($role) {
            'admin' => User::factory()->admin()->create(),
            'gestor' => User::factory()->gestor()->create(),
            default => User::factory()->create(),
        };

        $this->actingAs($user)->get($uri)->assertStatus($status);
    }

    #[Test]
    public function membro_nao_cria_escala(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/escalas', ['month' => '2026-12'])
            ->assertForbidden();
    }

    #[Test]
    public function usuario_inativo_e_bloqueado(): void
    {
        $this->actingAs(User::factory()->create(['active' => false]))
            ->get('/escalas')
            ->assertForbidden();
    }

    #[Test]
    public function gestor_nao_enxerga_escala_de_outra_unidade(): void
    {
        $gestor = User::factory()->gestor()->create();
        $other = Schedule::factory()->create(['unit_id' => Unit::factory()]);

        // o escopo global faz o route model binding não encontrar o registro
        $this->actingAs($gestor)->get("/escalas/{$other->id}")->assertNotFound();
    }

    #[Test]
    public function listagem_de_escalas_e_isolada_por_unidade(): void
    {
        $gestor = User::factory()->gestor()->create();
        Schedule::factory()->create(['unit_id' => $gestor->unit_id]);
        Schedule::factory()->count(3)->create();

        $this->actingAs($gestor)->get('/escalas')
            ->assertInertia(fn ($page) => $page->component('Schedules/Index')->has('schedules.data', 1));
    }

    #[Test]
    public function admin_enxerga_todas_as_unidades(): void
    {
        Schedule::factory()->count(3)->create();

        $this->actingAs(User::factory()->admin()->create())->get('/escalas')
            ->assertInertia(fn ($page) => $page->has('schedules.data', 3));
    }

    #[Test]
    public function gestor_cria_escala_sempre_na_propria_unidade(): void
    {
        $gestor = User::factory()->gestor()->create();
        $foreign = Unit::factory()->create();

        $this->actingAs($gestor)->post('/escalas', ['month' => '2026-12', 'unit_id' => $foreign->id])->assertRedirect();

        $this->assertDatabaseHas('schedules', ['unit_id' => $gestor->unit_id]);
        $this->assertDatabaseMissing('schedules', ['unit_id' => $foreign->id]);
    }

    #[Test]
    public function gestor_nao_promove_membro_a_admin(): void
    {
        $gestor = User::factory()->gestor()->create();
        $member = User::factory()->create(['unit_id' => $gestor->unit_id]);

        $this->actingAs($gestor)->patch("/membros/{$member->id}", ['role' => 'admin'])->assertSessionHasErrors('role');
        $this->assertSame('membro', $member->fresh()->role->value);
    }

    #[Test]
    public function gestor_nao_altera_membro_de_outra_unidade(): void
    {
        $gestor = User::factory()->gestor()->create();
        $stranger = User::factory()->create();

        $this->actingAs($gestor)->patch("/membros/{$stranger->id}", ['active' => false])->assertForbidden();
    }

    #[Test]
    public function horizon_restrito_ao_admin(): void
    {
        $this->actingAs(User::factory()->gestor()->create());
        $this->assertFalse(Gate::allows('viewHorizon'));

        $this->actingAs(User::factory()->admin()->create());
        $this->assertTrue(Gate::allows('viewHorizon'));
    }
}
