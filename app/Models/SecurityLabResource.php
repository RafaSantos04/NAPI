<?php

namespace App\Models;

use Database\Factories\SecurityLabResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Synthetic, clearly fictitious document owned by a Security Lab persona.
 * The only kind of record a deliberately vulnerable scenario may read.
 */
#[Fillable(['owner_user_id', 'name', 'content'])]
class SecurityLabResource extends Model
{
    /** @use HasFactory<SecurityLabResourceFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id')->withTrashed();
    }
}
