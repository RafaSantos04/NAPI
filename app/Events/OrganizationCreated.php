<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\Organization;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrganizationCreated implements Auditable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Organization $organization,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'organization_created',
            'subject_type' => Organization::class,
            'subject_id' => $this->organization->id,
        ];
    }
}
