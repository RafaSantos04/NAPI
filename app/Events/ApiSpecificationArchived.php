<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\ApiSpecification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApiSpecificationArchived implements Auditable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ApiSpecification $specification,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'api_specification_archived',
            'subject_type' => ApiSpecification::class,
            'subject_id' => $this->specification->id,
        ];
    }
}
