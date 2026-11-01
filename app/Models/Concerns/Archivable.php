<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Lifecycle of the project tree (ADR-0012): `archived_at` instead of
 * deletion. The listings filter by it with the `status` the index requests
 * accept: `active` (the default), `archived` or `all`.
 */
trait Archivable
{
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'active' => $query->whereNull('archived_at'),
            'archived' => $query->whereNotNull('archived_at'),
            default => $query,
        };
    }
}
