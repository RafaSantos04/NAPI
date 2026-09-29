<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\Profile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PermissionChanged implements Auditable
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int|string, array{can_view: bool, can_create: bool, can_update: bool, can_delete: bool}>  $permissions
     */
    public function __construct(
        public Profile $profile,
        public array $permissions,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'permission_changed',
            'subject_type' => Profile::class,
            'subject_id' => $this->profile->id,
            'meta' => ['permissions' => $this->permissions],
        ];
    }
}
