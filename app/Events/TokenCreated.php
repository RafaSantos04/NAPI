<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Laravel\Sanctum\PersonalAccessToken;

class TokenCreated implements Auditable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $owner,
        public PersonalAccessToken $token,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        // Token ids are integers, so they go in meta; subject_id is a ULID.
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'token_created',
            'subject_type' => User::class,
            'subject_id' => $this->owner->id,
            'meta' => [
                'token_id' => $this->token->id,
                'name' => $this->token->name,
                'abilities' => $this->token->abilities,
                'expires_at' => $this->token->expires_at?->toIso8601String(),
            ],
        ];
    }
}
