<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\SecurityTestRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An operator ran a Security Lab test. The audit entry says who ran what
 * and points at the run; the details of what the test observed stay in
 * security_test_runs.
 */
class SecurityTestExecuted implements Auditable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SecurityTestRun $run,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'security_test_executed',
            'subject_type' => SecurityTestRun::class,
            'subject_id' => $this->run->id,
            'meta' => [
                'test_key' => $this->run->test_key->value,
                'scenario' => $this->run->scenario->value,
                'acting_as_user_id' => $this->run->acting_as_user_id,
                'verdict' => $this->run->security_verdict->value,
            ],
        ];
    }
}
