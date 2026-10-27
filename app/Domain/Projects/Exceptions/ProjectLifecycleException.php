<?php

namespace App\Domain\Projects\Exceptions;

use App\Models\Project;
use DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An archived project is read-only until restored, and only an archived one
 * can be restored (ADR-0012). 409 on the API, like the other invariants.
 */
class ProjectLifecycleException extends DomainException
{
    public function __construct(public readonly Project $project, string $message)
    {
        parent::__construct($message);
    }

    public static function archived(Project $project): self
    {
        return new self($project, 'The project is archived.');
    }

    public static function notArchived(Project $project): self
    {
        return new self($project, 'The project is not archived.');
    }

    public function render(): Response
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
