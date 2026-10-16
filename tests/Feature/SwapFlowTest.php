<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use App\Models\SwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SwapFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $gestor;

    private User $alice;

    private User $bruno;

    private ShiftAssignment $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gestor = User::factory()->gestor()->create();
        $unit = $this->gestor->unit_id;
        $this->alice = User::factory()->create(['unit_id' => $unit]);
        $this->bruno = User::factory()->create(['unit_id' => $unit]);

        $schedule = Schedule::factory()->published()->create(['unit_id' => $unit]);
        $this->shift = ShiftAssignment::factory()->create([
            'schedule_id' => $schedule->id,
            'shift_type_id' => ShiftType::factory()->create(['unit_id' => $unit])->id,
            'user_id' => $this->alice->id,
        ]);
    }

    private function requestSwap(): SwapRequest
    {
        $this->actingAs($this->alice)->post('/trocas', [
            'shift_assignment_id' => $this->shift->id,
            'target_user_id' => $this->bruno->id,
            'reason' => 'Consulta médica',
        ])->assertSessionHas('success');

        return SwapRequest::latest('id')->firstOrFail();
    }

    #[Test]
    public function fluxo_completo_solicita_aceita_aprova(): void
    {
        $swap = $this->requestSwap();

        $this->actingAs($this->bruno)->post("/trocas/{$swap->id}/responder", ['accept' => true]);
        $this->assertSame('aceita', $swap->fresh()->status->value);

        $this->actingAs($this->gestor)->post("/trocas/{$swap->id}/decidir", ['approve' => true]);

        $this->assertSame('aprovada', $swap->fresh()->status->value);
        $this->assertSame($this->bruno->id, $this->shift->fresh()->user_id);
        $this->assertSame($this->gestor->id, $swap->fresh()->decided_by);
    }

    #[Test]
    public function listagem_mostra_botao_aprovar_para_o_gestor(): void
    {
        $swap = $this->requestSwap();
        $this->actingAs($this->bruno)->post("/trocas/{$swap->id}/responder", ['accept' => true]);

        $this->actingAs($this->gestor)->get('/trocas')
            ->assertInertia(fn ($page) => $page->where('swaps.data.0.can.decide', true));
    }

    #[Test]
    public function gestor_nao_aprova_antes_do_colega_aceitar(): void
    {
        $swap = $this->requestSwap();

        $this->actingAs($this->gestor)->post("/trocas/{$swap->id}/decidir", ['approve' => true])->assertForbidden();
        $this->assertSame($this->alice->id, $this->shift->fresh()->user_id);
    }

    #[Test]
    public function so_o_dono_do_plantao_pede_troca(): void
    {
        $this->actingAs($this->bruno)->post('/trocas', [
            'shift_assignment_id' => $this->shift->id,
            'target_user_id' => $this->alice->id,
        ])->assertForbidden();
    }

    #[Test]
    public function nao_troca_com_colega_de_outra_unidade(): void
    {
        $this->actingAs($this->alice)->post('/trocas', [
            'shift_assignment_id' => $this->shift->id,
            'target_user_id' => User::factory()->create()->id,
        ])->assertSessionHasErrors('target_user_id');
    }

    #[Test]
    public function nao_troca_com_colega_que_ja_tem_plantao_no_dia(): void
    {
        ShiftAssignment::factory()->create([
            'schedule_id' => $this->shift->schedule_id,
            'shift_type_id' => $this->shift->shift_type_id,
            'user_id' => $this->bruno->id,
            'date' => $this->shift->date,
        ]);

        $this->actingAs($this->alice)->post('/trocas', [
            'shift_assignment_id' => $this->shift->id,
            'target_user_id' => $this->bruno->id,
        ])->assertSessionHasErrors('target_user_id');
    }

    #[Test]
    public function nao_abre_duas_trocas_para_o_mesmo_plantao(): void
    {
        $this->requestSwap();
        $carla = User::factory()->create(['unit_id' => $this->gestor->unit_id]);

        $this->actingAs($this->alice)->post('/trocas', [
            'shift_assignment_id' => $this->shift->id,
            'target_user_id' => $carla->id,
        ])->assertSessionHasErrors('shift_assignment_id');
    }

    #[Test]
    public function solicitante_pode_cancelar(): void
    {
        $swap = $this->requestSwap();

        $this->actingAs($this->alice)->post("/trocas/{$swap->id}/cancelar");
        $this->assertSame('cancelada', $swap->fresh()->status->value);
    }

    #[Test]
    public function gestor_de_outra_unidade_nao_decide(): void
    {
        $swap = $this->requestSwap();
        $this->actingAs($this->bruno)->post("/trocas/{$swap->id}/responder", ['accept' => true]);

        $this->actingAs(User::factory()->gestor()->create())
            ->post("/trocas/{$swap->id}/decidir", ['approve' => true])
            ->assertForbidden();
    }
}
