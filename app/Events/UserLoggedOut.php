<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserLoggedOut implements Auditable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public int $revokedTokens,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'logout',
            'meta' => ['revoked_tokens' => $this->revokedTokens],
        ];
    }
}
