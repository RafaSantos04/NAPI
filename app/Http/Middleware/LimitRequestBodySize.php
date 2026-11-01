<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Default body limit for the api and web groups (ADR-0013, items 22 to 25):
 * the largest legitimate body today is about 20 KB. Laravel's
 * ValidatePostSize only compares Content-Length with php.ini's
 * post_max_size, so a chunked body (no Content-Length) passes it; this one
 * also measures the body itself, reading at most one byte past the limit.
 *
 * A route that needs more (the P3 import) states its own limit; none does
 * yet. Multipart bodies are parsed by PHP before this runs, so they rely on
 * Content-Length and the server limits.
 */
class LimitRequestBodySize
{
    public const MAX_BYTES = 64 * 1024;

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->exceedsLimit($request)) {
            throw new PostTooLargeException('The request body is too large.');
        }

        return $next($request);
    }

    private function exceedsLimit(Request $request): bool
    {
        $declared = $request->server('CONTENT_LENGTH');

        if (is_numeric($declared)) {
            return (int) $declared > self::MAX_BYTES;
        }

        $body = $request->getContent(true);

        return strlen((string) stream_get_contents($body, self::MAX_BYTES + 1)) > self::MAX_BYTES;
    }
}
