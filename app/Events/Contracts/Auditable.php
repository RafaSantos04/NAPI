<?php

namespace App\Events\Contracts;

/**
 * A domain event that is recorded in audit_logs. RecordAuditLog listens to
 * this contract, so every implementing event produces exactly one entry.
 */
interface Auditable
{
    /**
     * Attributes of the AuditLog row describing this fact.
     *
     * @return array{user_id: ?string, action: string, subject_type?: ?string, subject_id?: ?string, ip: ?string, user_agent: ?string, meta?: array<string, mixed>|null}
     */
    public function auditEntry(): array;
}
