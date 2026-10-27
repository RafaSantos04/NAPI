<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\Environment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EnvironmentUpdated implements Auditable
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $fields
     */
    public function __construct(
        public Environment $environment,
        public array $fields,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'environment_updated',
            'subject_type' => Environment::class,
            'subject_id' => $this->environment->id,
            'meta' => ['fields' => $this->fields],
        ];
    }
}
