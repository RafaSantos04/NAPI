<?php

namespace App\Listeners;

use App\Events\ProfileAssigned;
use App\Models\AuditLog;
use App\Models\User;

class AuditProfileAssignment
{
    public function handle(ProfileAssigned $event): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'profile_assigned',
            'subject_type' => User::class,
            'subject_id' => $event->user->id,
            'meta' => [
                'profile_ids' => $event->profileIds,
            ],
            'ip' => $event->ip,
        ]);
    }
}
