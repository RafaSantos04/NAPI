<?php

namespace App\Domain\ApiInventory\Exceptions;

use App\Models\ApiSpecification;
use DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An archived specification is read-only until restored, and only an
 * archived one can be restored (ADR-0014). Archived ancestors are reported
 * by the project tree exceptions instead.
 */
class ApiSpecificationLifecycleException extends DomainException
{
    public function __construct(public readonly ApiSpecification $specification, string $message)
    {
        parent::__construct($message);
    }

    public static function archived(ApiSpecification $specification): self
    {
        return new self($specification, 'The API specification is archived.');
    }

    public static function notArchived(ApiSpecification $specification): self
    {
        return new self($specification, 'The API specification is not archived.');
    }

    public function render(): Response
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
