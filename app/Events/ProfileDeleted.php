<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\Profile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Profiles are deleted for good (no soft delete), and their matrix rows go
 * with them through the foreign key.
 */
class ProfileDeleted implements Auditable
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
            'action' => 'profile_deleted',
            'subject_type' => Profile::class,
            'subject_id' => $this->profile->id,
            // The row is gone, so the entry says which profile it was.
            'meta' => ['slug' => $this->profile->slug, 'name' => $this->profile->name],
        ];
    }
}
