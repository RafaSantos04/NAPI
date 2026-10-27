<?php

namespace App\Domain\Projects\Exceptions;

use App\Models\Environment;
use DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An archived environment is read-only until restored, and only an archived
 * one can be restored (ADR-0012). Archived ancestors are reported by their
 * own lifecycle exceptions.
 */
class EnvironmentLifecycleException extends DomainException
{
    public function __construct(public readonly Environment $environment, string $message)
    {
        parent::__construct($message);
    }

    public static function archived(Environment $environment): self
    {
        return new self($environment, 'The environment is archived.');
    }

    public static function notArchived(Environment $environment): self
    {
        return new self($environment, 'The environment is not archived.');
    }

    public function render(): Response
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
