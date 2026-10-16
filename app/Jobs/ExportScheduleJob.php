<?php

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Exports\ScheduleSheet;
use App\Models\ScheduleExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Exporta a escala em XLSX ou PDF fora do ciclo da requisição.
 * O progresso fica em schedule_exports.progress e a tela consulta por polling.
 */
class ExportScheduleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public ScheduleExport $export)
    {
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $export = $this->export;
        $export->update(['status' => ExportStatus::Processando, 'progress' => 5]);

        $schedule = $export->schedule()->withoutGlobalScopes()->with('unit')->firstOrFail();

        $rows = [];
        $query = $schedule->assignments()->with(['user:id,name,registration', 'shiftType:id,name,starts_at,duration_hours'])->orderBy('date')->orderBy('shift_type_id');
        $total = max(1, $query->count());
        $done = 0;

        // processa em lotes e reporta progresso de 5% a 90%
        $query->chunk(200, function ($chunk) use (&$rows, &$done, $total, $export) {
            foreach ($chunk as $a) {
                $rows[] = [
                    $a->date->format('d/m/Y'),
                    $a->date->locale('pt_BR')->isoFormat('ddd'),
                    $a->shiftType->name,
                    substr($a->shiftType->starts_at, 0, 5),
                    $a->shiftType->duration_hours.'h',
                    $a->user->name,
                    $a->user->registration,
                ];
            }
            $done += $chunk->count();
            $export->update(['progress' => 5 + (int) (85 * $done / $total)]);
        });

        $name = sprintf('exports/escala-%s-%s-%d.%s', $schedule->unit->code, $schedule->month->format('Y-m'), $export->id, $export->format);

        if ($export->format === 'pdf') {
            $pdf = Pdf::loadView('exports.schedule', ['schedule' => $schedule, 'rows' => $rows])->setPaper('a4');
            Storage::disk('local')->put($name, $pdf->output());
        } else {
            Excel::store(new ScheduleSheet($rows), $name, 'local');
        }

        $export->update(['status' => ExportStatus::Concluida, 'progress' => 100, 'path' => $name]);
    }

    public function failed(Throwable $e): void
    {
        $this->export->update(['status' => ExportStatus::Falhou, 'error' => $e->getMessage()]);
    }
}
