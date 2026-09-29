<?php

namespace App\Domain\IAM\Exceptions;

use DomainException;
use Illuminate\Http\JsonResponse;

/**
 * The operation would leave the system without an active administrator.
 * A known business rule, so it is a 409 Conflict with the current state,
 * never a 500 (FIND-006).
 */
class LastActiveAdministratorException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The system must keep at least one active administrator.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
