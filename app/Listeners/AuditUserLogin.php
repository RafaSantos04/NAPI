<?php

namespace App\Listeners;

use App\Events\UserLoggedIn;
use App\Models\AuditLog;

class AuditUserLogin
{
    public function handle(UserLoggedIn $event): void
    {
        AuditLog::create([
            'user_id' => $event->user->id,
            'action' => 'login',
            'ip' => $event->ip,
            'user_agent' => $event->userAgent,
        ]);
    }
}
