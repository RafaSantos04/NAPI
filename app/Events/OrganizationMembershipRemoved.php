<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\OrganizationMembership;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrganizationMembershipRemoved implements Auditable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public OrganizationMembership $membership,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'membership_removed',
            'subject_type' => OrganizationMembership::class,
            'subject_id' => $this->membership->id,
            'meta' => ['user_id' => $this->membership->user_id],
        ];
    }
}
