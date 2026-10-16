<?php

namespace App\Models;

use App\Enums\Module;
use App\Enums\Role;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    protected $fillable = ['unit_id', 'name', 'email', 'role', 'registration', 'active', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function unavailabilities(): HasMany
    {
        return $this->hasMany(Unavailability::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isGestor(): bool
    {
        return $this->role === Role::Gestor;
    }

    /** Consulta a matriz perfil x módulo de config/rbac.php. */
    public function canAccessModule(Module $module, string $level = 'read'): bool
    {
        $levels = config('rbac.levels');
        $granted = config("rbac.matrix.{$this->role->value}.{$module->value}", 'none');

        return $levels[$granted] >= $levels[$level];
    }

    /** Usuários visíveis para quem consulta (mesma unidade, ou todos para admin). */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return $viewer->isAdmin() ? $query : $query->where('unit_id', $viewer->unit_id);
    }

    public function sameUnitAs(?int $unitId): bool
    {
        return $this->isAdmin() || ($unitId !== null && $this->unit_id === $unitId);
    }
}
