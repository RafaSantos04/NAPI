<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\Profile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfileUpdated implements Auditable
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $fields  Names of the changed attributes.
     */
    public function __construct(
        public Profile $profile,
        public array $fields,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'profile_updated',
            'subject_type' => Profile::class,
            'subject_id' => $this->profile->id,
            'meta' => ['fields' => $this->fields],
        ];
    }
}
