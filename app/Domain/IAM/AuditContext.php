<?php

namespace App\Domain\IAM;

use Illuminate\Http\Request;

/**
 * Who performed an operation and from where. Captured at the boundary and
 * carried by the domain events, so audit listeners never reach for auth()
 * or request() and keep working outside an HTTP request (FIND-013).
 */
final readonly class AuditContext
{
    public function __construct(
        public ?string $actorId,
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self($request->user()?->id, $request->ip(), $request->userAgent());
    }

    /**
     * @return array{user_id: ?string, ip: ?string, user_agent: ?string}
     */
    public function toAuditAttributes(): array
    {
        return [
            'user_id' => $this->actorId,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
        ];
    }

    /**
     * Operations not triggered by a user (console, seeders, jobs).
     */
    public static function system(): self
    {
        return new self(null);
    }
}
