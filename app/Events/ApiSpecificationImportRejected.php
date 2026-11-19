<?php

namespace App\Events;

use App\Domain\ApiInventory\Exceptions\OpenApiParseException;
use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\ApiSpecification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * An authorized import the pipeline refused with 422 (ADR-0014, decision 20).
 * Written outside the import transaction, since nothing else is.
 */
class ApiSpecificationImportRejected implements Auditable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ApiSpecification $specification,
        public OpenApiParseException $rejection,
        public string $format,
        public string $rawDocument,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'api_specification_import_rejected',
            'subject_type' => ApiSpecification::class,
            'subject_id' => $this->specification->id,
            // Stable reason, never the document nor the parser's own message.
            // The hash correlates repeated attempts; the pointer is capped,
            // since a path name is document content.
            'meta' => [
                'reason' => $this->rejection->reason,
                'pointer' => Str::limit($this->rejection->pointer, 255),
                'format' => $this->format,
                'size_bytes' => strlen($this->rawDocument),
                'content_sha256' => hash('sha256', $this->rawDocument),
            ],
        ];
    }
}
