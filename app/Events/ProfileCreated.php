<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\Profile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfileCreated implements Auditable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Profile $profile,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'profile_created',
            'subject_type' => Profile::class,
            'subject_id' => $this->profile->id,
        ];
    }
}
