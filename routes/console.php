<?php

use App\Models\AuditLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retenção da auditoria (config/audit.php) e métricas do Horizon
Schedule::command('model:prune', ['--model' => [AuditLog::class]])->daily();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
