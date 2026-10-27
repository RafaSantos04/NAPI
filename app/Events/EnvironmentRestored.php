<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\Environment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EnvironmentRestored implements Auditable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Environment $environment,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'environment_restored',
            'subject_type' => Environment::class,
            'subject_id' => $this->environment->id,
        ];
    }
}
