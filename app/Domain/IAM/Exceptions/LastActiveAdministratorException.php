<?php

namespace App\Domain\IAM\Exceptions;

use DomainException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The operation would leave the system without an active administrator.
 * A known business rule, so it is a 409 Conflict with the current state,
 * never a 500 (FIND-006).
 *
 * One exception feeds both adapters: the API answers 409, the admin area
 * goes back with the conflict as a flash message (Phase 4.2).
 */
class LastActiveAdministratorException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The system must keep at least one active administrator.');
    }

    public function render(Request $request): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 409);
        }

        return back()->with('error', 'O sistema precisa manter ao menos um administrador ativo.');
    }
}
