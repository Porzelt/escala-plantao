<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleExportController;
use App\Http\Controllers\SwapRequestController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('module:escalas,read')->group(function () {
        Route::get('/escalas', [ScheduleController::class, 'index'])->name('schedules.index');
        Route::get('/escalas/{schedule}', [ScheduleController::class, 'show'])->name('schedules.show');
        Route::post('/escalas/{schedule}/exportar', [ScheduleExportController::class, 'store'])
            ->middleware('throttle:10,1')->name('schedules.export');
        Route::get('/exportacoes/{export}', [ScheduleExportController::class, 'show'])->name('exports.show');
        Route::get('/exportacoes/{export}/download', [ScheduleExportController::class, 'download'])->name('exports.download');
    });

    Route::middleware('module:escalas,write')->group(function () {
        Route::post('/escalas', [ScheduleController::class, 'store'])->name('schedules.store');
        Route::post('/escalas/{schedule}/gerar', [ScheduleController::class, 'generate'])->name('schedules.generate');
        Route::post('/escalas/{schedule}/publicar', [ScheduleController::class, 'publish'])->name('schedules.publish');
    });

    Route::middleware('module:trocas,write')->group(function () {
        Route::get('/trocas', [SwapRequestController::class, 'index'])->name('swaps.index');
        Route::post('/trocas', [SwapRequestController::class, 'store'])->name('swaps.store');
        Route::post('/trocas/{swap}/responder', [SwapRequestController::class, 'respond'])->name('swaps.respond');
        Route::post('/trocas/{swap}/decidir', [SwapRequestController::class, 'decide'])->name('swaps.decide');
        Route::post('/trocas/{swap}/cancelar', [SwapRequestController::class, 'cancel'])->name('swaps.cancel');
    });

    Route::get('/membros', [MemberController::class, 'index'])->middleware('module:membros,read')->name('members.index');
    Route::middleware('module:membros,write')->group(function () {
        Route::post('/membros', [MemberController::class, 'store'])->name('members.store');
        Route::patch('/membros/{member}', [MemberController::class, 'update'])->name('members.update');
    });

    Route::get('/auditoria', [AuditLogController::class, 'index'])->middleware('module:auditoria,read')->name('audit.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
