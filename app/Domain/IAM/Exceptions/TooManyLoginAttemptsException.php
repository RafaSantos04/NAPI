<?php

namespace App\Domain\IAM\Exceptions;

use DomainException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A password check refused by the login counters before the hash ran
 * (ADR-0013). A known outcome, so a 429 with Retry-After, never a 500.
 *
 * One exception feeds both adapters: the API answers 429, the admin area
 * goes back to the login form with the wait in seconds.
 */
class TooManyLoginAttemptsException extends DomainException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Too many login attempts. Try again later.');
    }

    public function render(Request $request): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 429, ['Retry-After' => $this->retryAfter]);
        }

        return back()
            ->withInput($request->except('password'))
            ->withErrors(['email' => "Muitas tentativas. Tente novamente em {$this->retryAfter} segundos."]);
    }
}
