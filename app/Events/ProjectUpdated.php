<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectUpdated implements Auditable
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $fields
     */
    public function __construct(
        public Project $project,
        public array $fields,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'project_updated',
            'subject_type' => Project::class,
            'subject_id' => $this->project->id,
            'meta' => ['fields' => $this->fields],
        ];
    }
}
