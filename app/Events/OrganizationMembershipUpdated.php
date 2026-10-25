<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\OrganizationMembership;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrganizationMembershipUpdated implements Auditable
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public OrganizationMembership $membership,
        public array $changes,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'membership_updated',
            'subject_type' => OrganizationMembership::class,
            'subject_id' => $this->membership->id,
            'meta' => [
                'user_id' => $this->membership->user_id,
                'changes' => $this->changes,
            ],
        ];
    }
}
