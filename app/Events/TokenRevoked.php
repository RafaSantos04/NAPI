<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Laravel\Sanctum\PersonalAccessToken;

class TokenRevoked implements Auditable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $owner,
        public PersonalAccessToken $token,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'token_revoked',
            'subject_type' => User::class,
            'subject_id' => $this->owner->id,
            'meta' => [
                'token_id' => $this->token->id,
                'name' => $this->token->name,
            ],
        ];
    }
}
