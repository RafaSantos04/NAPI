<?php

namespace App\Exceptions;

use App\Models\Organization;
use DomainException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LastActiveOrganizationOwnerException extends DomainException
{
    public function __construct(public readonly Organization $organization)
    {
        parent::__construct('The organization must keep at least one active owner.');
    }

    public function render(Request $request): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 409);
        }

        return back()->with('error', 'A organização precisa manter ao menos um responsável ativo.');
    }
}
