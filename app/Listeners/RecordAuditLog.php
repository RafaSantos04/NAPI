<?php

namespace App\Listeners;

use App\Events\Contracts\Auditable;
use App\Models\AuditLog;

/**
 * Writes one audit_logs row per Auditable domain event. Registered only by
 * event discovery (FIND-002). Runs synchronously, so when the event is
 * dispatched inside the operation's transaction the entry commits or rolls
 * back together with the state change (FIND-013).
 */
class RecordAuditLog
{
    public function handle(Auditable $event): void
    {
        AuditLog::create($event->auditEntry());
    }
}
