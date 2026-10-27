<?php

namespace App\Events;

use App\Domain\IAM\AuditContext;
use App\Events\Contracts\Auditable;
use App\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectArchived implements Auditable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Project $project,
        public AuditContext $context,
    ) {}

    public function auditEntry(): array
    {
        return [
            ...$this->context->toAuditAttributes(),
            'action' => 'project_archived',
            'subject_type' => Project::class,
            'subject_id' => $this->project->id,
        ];
    }
}
