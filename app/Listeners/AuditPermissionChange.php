<?php

namespace App\Listeners;

use App\Events\PermissionChanged;
use App\Models\AuditLog;
use App\Models\Profile;

class AuditPermissionChange
{
    public function handle(PermissionChanged $event): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'permission_changed',
            'subject_type' => Profile::class,
            'subject_id' => $event->profile->id,
            'meta' => [
                'permissions' => $event->permissions,
            ],
            'ip' => $event->ip,
        ]);
    }
}
