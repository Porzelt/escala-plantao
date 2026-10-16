<?php

namespace Tests\Feature;

use App\Enums\ExportStatus;
use App\Jobs\ExportScheduleJob;
use App\Models\Schedule;
use App\Models\ScheduleExport;
use App\Models\ShiftType;
use App\Models\Unavailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScheduleFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $gestor;

    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gestor = User::factory()->gestor()->create();
        User::factory()->count(8)->create(['unit_id' => $this->gestor->unit_id]);
        ShiftType::factory()->create(['unit_id' => $this->gestor->unit_id]);
        ShiftType::factory()->noturno()->create(['unit_id' => $this->gestor->unit_id]);
        $this->schedule = Schedule::factory()->create(['unit_id' => $this->gestor->unit_id, 'month' => '2026-11-01']);
    }

    #[Test]
    public function gera_escala_completa_e_equilibrada(): void
    {
        $this->actingAs($this->gestor)
            ->post("/escalas/{$this->schedule->id}/gerar", ['seed' => 123])
            ->assertSessionHas('success');

        $this->assertSame(60, $this->schedule->assignments()->count()); // 30 dias x 2 turnos
        $this->assertSame(123, $this->schedule->fresh()->seed);

        $counts = $this->schedule->assignments()->selectRaw('user_id, count(*) c')->groupBy('user_id')->pluck('c');
        // o equilíbrio é por carga ponderada (noturno/fim de semana pesam mais), então a contagem varia pouco
        $this->assertLessThanOrEqual(3, $counts->max() - $counts->min());
    }

    #[Test]
    public function regerar_com_mesma_semente_e_reprodutivel(): void
    {
        $this->actingAs($this->gestor);
        $snapshot = function () {
            return $this->schedule->assignments()->orderBy('date')->orderBy('shift_type_id')->pluck('user_id')->all();
        };

        $this->post("/escalas/{$this->schedule->id}/gerar", ['seed' => 9]);
        $first = $snapshot();
        $this->post("/escalas/{$this->schedule->id}/gerar", ['seed' => 9]);

        $this->assertSame($first, $snapshot());
    }

    #[Test]
    public function indisponibilidade_e_respeitada_na_geracao(): void
    {
        $member = User::where('unit_id', $this->gestor->unit_id)->where('role', 'membro')->first();
        Unavailability::create(['user_id' => $member->id, 'date' => '2026-11-15']);

        $this->actingAs($this->gestor)->post("/escalas/{$this->schedule->id}/gerar");

        $this->assertFalse($this->schedule->assignments()->where('user_id', $member->id)->whereDate('date', '2026-11-15')->exists());
    }

    #[Test]
    public function escala_publicada_nao_pode_ser_regerada(): void
    {
        $this->actingAs($this->gestor)->post("/escalas/{$this->schedule->id}/gerar");
        $this->post("/escalas/{$this->schedule->id}/publicar")->assertSessionHas('success');

        $this->post("/escalas/{$this->schedule->id}/gerar")->assertForbidden();
    }

    #[Test]
    public function nao_publica_escala_vazia(): void
    {
        $this->actingAs($this->gestor)->post("/escalas/{$this->schedule->id}/publicar")->assertForbidden();
    }

    #[Test]
    public function exportacao_vai_para_a_fila_exports(): void
    {
        Queue::fake();

        $this->actingAs($this->gestor)
            ->postJson("/escalas/{$this->schedule->id}/exportar", ['format' => 'xlsx'])
            ->assertAccepted()
            ->assertJsonPath('status', 'na_fila')
            ->assertJsonPath('progress', 0);

        Queue::assertPushedOn('exports', ExportScheduleJob::class);
    }

    #[Test]
    public function job_de_exportacao_gera_arquivo_e_chega_a_100(): void
    {
        Storage::fake('local');
        $this->actingAs($this->gestor)->post("/escalas/{$this->schedule->id}/gerar");

        foreach (['xlsx', 'pdf'] as $format) {
            $response = $this->postJson("/escalas/{$this->schedule->id}/exportar", ['format' => $format]); // fila sync nos testes
            $response->assertAccepted();
            $export = ScheduleExport::findOrFail($response->json('id'));

            $this->assertSame(ExportStatus::Concluida, $export->status);
            $this->assertSame(100, $export->progress);
            Storage::disk('local')->assertExists($export->path);

            $this->getJson("/exportacoes/{$export->id}")->assertJsonPath('progress', 100)->assertJsonPath('status', 'concluida');
            $this->get("/exportacoes/{$export->id}/download")->assertOk();
        }
    }

    #[Test]
    public function progresso_de_exportacao_e_privado(): void
    {
        $export = ScheduleExport::create(['schedule_id' => $this->schedule->id, 'user_id' => $this->gestor->id, 'format' => 'xlsx']);
        $other = User::factory()->create(['unit_id' => $this->gestor->unit_id]);

        $this->actingAs($other)->getJson("/exportacoes/{$export->id}")->assertNotFound();
    }

    #[Test]
    public function formato_invalido_e_rejeitado(): void
    {
        $this->actingAs($this->gestor)->postJson("/escalas/{$this->schedule->id}/exportar", ['format' => 'exe'])->assertUnprocessable();
    }

    #[Test]
    public function painel_carrega_indicadores(): void
    {
        $this->actingAs($this->gestor)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->component('Dashboard')->where('stats.members', 9)->has('nextShifts'));
    }
}
