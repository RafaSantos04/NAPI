<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationCreated implements Auditable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Application $application,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'application_created',
            'subject_type' => Application::class,
            'subject_id' => $this->application->id,
        ];
    }
}
