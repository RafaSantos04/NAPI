<?php

namespace App\Domain\ApiInventory\Exceptions;

use App\Models\Organization;
use DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * One import at a time per organization (ADR-0013 item 31, ADR-0014 decision
 * 17): a second one is refused at once instead of queueing behind the first.
 */
class ApiImportInProgressException extends DomainException
{
    public function __construct(public readonly Organization $organization)
    {
        parent::__construct('Another import is in progress in this organization. Try again shortly.');
    }

    public function render(): Response
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
