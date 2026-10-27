<?php

namespace App\Domain\Projects\Exceptions;

use App\Models\Application;
use DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An archived application is read-only until restored, and only an archived
 * one can be restored (ADR-0012). An archived parent project is reported by
 * ProjectLifecycleException instead.
 */
class ApplicationLifecycleException extends DomainException
{
    public function __construct(public readonly Application $application, string $message)
    {
        parent::__construct($message);
    }

    public static function archived(Application $application): self
    {
        return new self($application, 'The application is archived.');
    }

    public static function notArchived(Application $application): self
    {
        return new self($application, 'The application is not archived.');
    }

    public function render(): Response
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
