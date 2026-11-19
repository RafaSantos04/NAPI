<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\ApiSpecificationVersion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApiSpecificationVersionImported implements Auditable
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array{endpoints: int, added: int, removed: int}  $inventory  counts against the previous version
     */
    public function __construct(
        public ApiSpecificationVersion $version,
        public array $inventory,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'api_specification_version_imported',
            'subject_type' => ApiSpecificationVersion::class,
            'subject_id' => $this->version->id,
            // Never the document: it is the client's confidential data.
            'meta' => [
                'api_specification_id' => $this->version->api_specification_id,
                'number' => $this->version->number,
                'format' => $this->version->format,
                'openapi_version' => $this->version->openapi_version,
                'size_bytes' => $this->version->size_bytes,
                'content_sha256' => $this->version->content_sha256,
                'endpoints' => $this->inventory['endpoints'],
                'endpoints_added' => $this->inventory['added'],
                'endpoints_removed' => $this->inventory['removed'],
            ],
        ];
    }
}
