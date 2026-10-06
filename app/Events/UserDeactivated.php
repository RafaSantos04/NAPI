<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserDeactivated implements Auditable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public int $revokedTokens,
        public int $endedSessions,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'user_deactivated',
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
            'meta' => [
                'revoked_tokens' => $this->revokedTokens,
                'ended_sessions' => $this->endedSessions,
            ],
        ];
    }
}
