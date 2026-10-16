<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('city')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role', 20)->default('membro')->after('email');
            $table->string('registration', 20)->nullable()->unique()->after('role');
            $table->boolean('active')->default(true)->after('registration');
            $table->index(['unit_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn(['role', 'registration', 'active']);
        });
        Schema::dropIfExists('units');
    }
};
