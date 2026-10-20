<?php

namespace App\Domain\IAM\Exceptions;

use DomainException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A record cannot be removed because others still depend on it: a profile
 * held by users, a menu with submenus. The Policies already refuse these
 * deletions; this is the same rule checked again under a lock, so a request
 * that slips in between the check and the delete is a 409, never a silent
 * cascade (Phases 4.3 and 4.4).
 *
 * Like LastActiveAdministratorException, one exception feeds both adapters.
 */
class ResourceInUseException extends DomainException
{
    private function __construct(string $message, private readonly string $adminMessage)
    {
        parent::__construct($message);
    }

    public static function profileHasUsers(): self
    {
        return new self(
            'The profile is assigned to users.',
            'O perfil está atribuído a usuários e não pode ser excluído.',
        );
    }

    public static function menuHasChildren(): self
    {
        return new self(
            'The menu has submenus.',
            'O menu tem submenus e não pode ser excluído.',
        );
    }

    public function render(Request $request): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 409);
        }

        return back()->with('error', $this->adminMessage);
    }
}
