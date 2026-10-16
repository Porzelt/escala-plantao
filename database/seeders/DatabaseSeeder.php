<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use App\Models\SwapRequest;
use App\Models\Unavailability;
use App\Models\Unit;
use App\Models\User;
use App\Services\ScheduleGenerator;
use Illuminate\Database\Seeder;

/**
 * Dados 100% fictícios. Senha de todos os usuários: "password".
 *   admin@escala.test    — administrador (todas as unidades)
 *   gestor1@escala.test  — gestor da primeira unidade
 *   membro1@escala.test  — membro da primeira unidade
 */
class DatabaseSeeder extends Seeder
{
    public function run(ScheduleGenerator $generator): void
    {
        User::factory()->admin()->create(['name' => 'Administração Central', 'email' => 'admin@escala.test']);

        $units = [
            ['Pronto Atendimento Norte', 'PA-NORTE', 'São Paulo', 22],
            ['Central de Monitoramento', 'CMON', 'Campinas', 16],
            ['Base Operacional Sul', 'BOP-SUL', 'Santos', 12],
        ];

        foreach ($units as $i => [$name, $code, $city, $size]) {
            $n = $i + 1;
            $unit = Unit::create(compact('name', 'code', 'city'));

            ShiftType::create(['unit_id' => $unit->id, 'name' => 'Diurno', 'starts_at' => '07:00', 'duration_hours' => 12, 'slots' => 2, 'weight' => 1]);
            ShiftType::create(['unit_id' => $unit->id, 'name' => 'Noturno', 'starts_at' => '19:00', 'duration_hours' => 12, 'slots' => 2, 'weight' => 1.5]);

            User::factory()->for($unit)->create(['role' => Role::Gestor, 'email' => "gestor{$n}@escala.test"]);
            $members = User::factory()->for($unit)->count($size)->sequence(fn ($s) => [
                'email' => $s->index === 0 ? "membro{$n}@escala.test" : "membro{$n}.{$s->index}@escala.test",
            ])->create();

            // férias/folgas aleatórias
            foreach ($members->random(min(5, $size)) as $m) {
                $start = now()->startOfMonth()->addDays(random_int(0, 20));
                foreach (range(0, random_int(1, 4)) as $d) {
                    Unavailability::firstOrCreate(['user_id' => $m->id, 'date' => $start->copy()->addDays($d)->toDateString()], ['reason' => 'Férias']);
                }
            }

            $previous = Schedule::create(['unit_id' => $unit->id, 'month' => now()->subMonthNoOverflow()->startOfMonth(), 'status' => ScheduleStatus::Publicada, 'published_at' => now()->subMonth()]);
            $generator->generate($previous, seed: 1000 + $n);

            $current = Schedule::create(['unit_id' => $unit->id, 'month' => now()->startOfMonth(), 'status' => ScheduleStatus::Publicada, 'published_at' => now()->startOfMonth()]);
            $generator->generate($current, seed: 2000 + $n);

            Schedule::create(['unit_id' => $unit->id, 'month' => now()->addMonthNoOverflow()->startOfMonth(), 'status' => ScheduleStatus::Rascunho]);

            // algumas trocas em andamento
            $upcoming = ShiftAssignment::where('schedule_id', $current->id)->whereDate('date', '>', today())->inRandomOrder()->limit(3)->get();
            foreach ($upcoming as $k => $a) {
                $target = $members->where('id', '!=', $a->user_id)->random();
                SwapRequest::create([
                    'shift_assignment_id' => $a->id,
                    'requester_id' => $a->user_id,
                    'target_user_id' => $target->id,
                    'status' => $k === 0 ? 'aceita' : 'pendente',
                    'reason' => 'Compromisso pessoal',
                ]);
            }
        }
    }
}
