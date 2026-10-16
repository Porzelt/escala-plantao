<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\Schedule;
use App\Models\User;

class SchedulePolicy
{
    public function view(User $user, Schedule $schedule): bool
    {
        return $user->sameUnitAs($schedule->unit_id) && $user->canAccessModule(Module::Escalas);
    }

    public function create(User $user): bool
    {
        return $user->canAccessModule(Module::Escalas, 'write');
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return $user->sameUnitAs($schedule->unit_id)
            && $user->canAccessModule(Module::Escalas, 'write')
            && ! $schedule->isPublished();
    }

    public function publish(User $user, Schedule $schedule): bool
    {
        return $this->update($user, $schedule) && $schedule->assignments()->exists();
    }

    public function export(User $user, Schedule $schedule): bool
    {
        return $this->view($user, $schedule);
    }
}
