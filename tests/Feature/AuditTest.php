<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function registra_quem_alterou_e_o_antes_depois(): void
    {
        $gestor = User::factory()->gestor()->create();
        $member = User::factory()->create(['unit_id' => $gestor->unit_id]);

        $this->actingAs($gestor)->patch("/membros/{$member->id}", ['role' => 'gestor']);

        $log = AuditLog::where('auditable_type', User::class)->where('auditable_id', $member->id)->where('event', 'updated')->firstOrFail();
        $this->assertSame($gestor->id, $log->user_id);
        $this->assertSame($gestor->unit_id, $log->unit_id);
        $this->assertSame(['role' => 'membro'], $log->old_values);
        $this->assertSame(['role' => 'gestor'], $log->new_values);
    }

    #[Test]
    public function nunca_grava_senha(): void
    {
        User::factory()->create();

        $log = AuditLog::where('event', 'created')->where('auditable_type', User::class)->firstOrFail();
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertArrayNotHasKey('remember_token', $log->new_values);
    }

    #[Test]
    public function gestor_ve_apenas_auditoria_da_propria_unidade(): void
    {
        $gestor = User::factory()->gestor()->create();
        Schedule::factory()->create(['unit_id' => $gestor->unit_id]);
        Schedule::factory()->create(); // outra unidade

        $this->actingAs($gestor)->get('/auditoria?type=Schedule')
            ->assertInertia(fn ($page) => $page->component('Audit/Index')->has('logs.data', 1));
    }

    #[Test]
    public function retencao_remove_registros_antigos(): void
    {
        config(['audit.retention_days' => 30]);
        User::factory()->create();
        AuditLog::query()->update(['created_at' => now()->subDays(31)]);
        User::factory()->create(); // gera logs recentes

        $before = AuditLog::count();
        $old = AuditLog::where('created_at', '<', now()->subDays(30))->count();

        $this->artisan('model:prune', ['--model' => [AuditLog::class]])->assertSuccessful();

        $this->assertSame($before - $old, AuditLog::count());
        $this->assertSame(0, AuditLog::where('created_at', '<', now()->subDays(30))->count());
    }
}
