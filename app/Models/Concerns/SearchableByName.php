<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait SearchableByName
{
    /**
     * Wildcards typed by the client are matched literally.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNameContains(Builder $query, ?string $search): Builder
    {
        return $query->when($search !== null, fn (Builder $query) => $query->whereLike(
            'name',
            '%'.addcslashes((string) $search, '\%_').'%',
        ));
    }
}
