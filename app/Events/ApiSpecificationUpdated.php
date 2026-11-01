<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\ApiSpecification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApiSpecificationUpdated implements Auditable
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $fields
     */
    public function __construct(
        public ApiSpecification $specification,
        public array $fields,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'api_specification_updated',
            'subject_type' => ApiSpecification::class,
            'subject_id' => $this->specification->id,
            'meta' => ['fields' => $this->fields],
        ];
    }
}
