<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Text that is not valid UTF-8 never reaches validation, route binding or
 * PostgreSQL, which rejects it with a 500 (FIND-026, ADR-0013 item 26). The
 * request is malformed rather than invalid, hence 400 and not 422.
 *
 * Checks query and form keys and values (nested arrays included) and a raw
 * JSON body. The path is already checked by Laravel's global
 * ValidatePathEncoding (400, "Malformed URL."). Uploaded files are binary
 * and stay out.
 */
class RejectInvalidUtf8
{
    public function handle(Request $request, Closure $next): Response
    {
        $isValid = $this->isValidUtf8($request->query->all())
            && $this->isValidUtf8($request->request->all())
            && (! $request->isJson() || mb_check_encoding($request->getContent(), 'UTF-8'));

        if (! $isValid) {
            throw new BadRequestHttpException('The request contains text that is not valid UTF-8.');
        }

        return $next($request);
    }

    /**
     * @param  array<array-key, mixed>  $input
     */
    private function isValidUtf8(array $input): bool
    {
        foreach ($input as $key => $value) {
            if (is_string($key) && ! mb_check_encoding($key, 'UTF-8')) {
                return false;
            }

            if (is_array($value) ? ! $this->isValidUtf8($value) : (is_string($value) && ! mb_check_encoding($value, 'UTF-8'))) {
                return false;
            }
        }

        return true;
    }
}
