<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->time('starts_at');
            $table->unsignedTinyInteger('duration_hours');
            $table->unsignedTinyInteger('slots')->default(1);   // pessoas por turno
            $table->decimal('weight', 4, 2)->default(1);         // peso para distribuição justa
            $table->timestamps();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->string('status', 20)->default('rascunho');
            $table->unsignedInteger('seed')->nullable();          // semente do sorteio (reprodutível)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['unit_id', 'month']);
        });

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();
            $table->unique(['schedule_id', 'shift_type_id', 'date', 'user_id']);
            $table->index(['user_id', 'date']);
        });

        Schema::create('unavailabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });

        Schema::create('swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pendente');
            $table->string('reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'target_user_id']);
        });

        Schema::create('schedule_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('format', 10);
            $table->string('status', 20)->default('na_fila');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('path')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['schedule_exports', 'swap_requests', 'unavailabilities', 'shift_assignments', 'schedules', 'shift_types'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
