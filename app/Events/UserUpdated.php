<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserUpdated implements Auditable
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $fields  Names of the changed attributes. Values
     *                                      are not recorded, to keep personal data
     *                                      out of the audit trail.
     */
    public function __construct(
        public User $user,
        public array $fields,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'user_updated',
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
            'meta' => ['fields' => $this->fields],
        ];
    }
}
