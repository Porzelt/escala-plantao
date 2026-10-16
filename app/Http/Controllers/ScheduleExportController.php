<?php

namespace App\Http\Controllers;

use App\Jobs\ExportScheduleJob;
use App\Models\Schedule;
use App\Models\ScheduleExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScheduleExportController extends Controller
{
    public function store(Request $request, Schedule $schedule): JsonResponse
    {
        Gate::authorize('export', $schedule);

        $format = $request->validate(['format' => ['required', 'in:xlsx,pdf']])['format'];

        $export = ScheduleExport::create([
            'schedule_id' => $schedule->id,
            'user_id' => $request->user()->id,
            'format' => $format,
        ]);

        ExportScheduleJob::dispatch($export);

        return response()->json($this->payload($export->fresh()), 202);
    }

    /** Endpoint de polling da barra de progresso. */
    public function show(Request $request, ScheduleExport $export): JsonResponse
    {
        abort_unless($export->user_id === $request->user()->id, 404);

        return response()->json($this->payload($export));
    }

    public function download(Request $request, ScheduleExport $export): StreamedResponse
    {
        abort_unless($export->user_id === $request->user()->id && $export->path, 404);

        return Storage::disk('local')->download($export->path);
    }

    private function payload(ScheduleExport $export): array
    {
        return [
            'id' => $export->id,
            'status' => $export->status->value,
            'progress' => $export->progress,
            'download_url' => $export->path ? route('exports.download', $export) : null,
            'error' => $export->error,
        ];
    }
}
