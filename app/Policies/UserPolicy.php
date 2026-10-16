<?php

namespace App\Policies;

use App\Enums\Module;
use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessModule(Module::Membros);
    }

    public function create(User $user): bool
    {
        return $user->canAccessModule(Module::Membros, 'write');
    }

    /** Gestor administra membros da própria unidade, mas não promove ninguém a admin. */
    public function update(User $user, User $member): bool
    {
        return $user->canAccessModule(Module::Membros, 'write')
            && $user->sameUnitAs($member->unit_id)
            && ($user->isAdmin() || $member->role !== Role::Admin);
    }
}
