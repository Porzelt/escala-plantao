<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = AuditLog::with('user:id,name')
            ->when($request->query('event'), fn ($q, $e) => $q->where('event', $e))
            ->when($request->query('type'), fn ($q, $t) => $q->whereLike('auditable_type', "%{$t}"))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Audit/Index', [
            'logs' => $logs->through(fn (AuditLog $l) => [
                'id' => $l->id,
                'when' => $l->created_at->format('d/m/Y H:i'),
                'user' => $l->user?->name ?? 'sistema',
                'event' => $l->event,
                'type' => class_basename($l->auditable_type),
                'auditable_id' => $l->auditable_id,
                'old' => $l->old_values,
                'new' => $l->new_values,
            ]),
            'filters' => $request->only('event', 'type'),
            'retentionDays' => config('audit.retention_days'),
        ]);
    }
}
