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
 * The only kind of record a deliberately vulnerable scenario may read or
 * write.
 *
 * `is_approved` is mass assignable on purpose, and only here: it is the
 * premise of the Mass Assignment test. The list below says what the model
 * accepts, not what each operation may change, and no real model is widened
 * for the lab.
 */
#[Fillable(['owner_user_id', 'name', 'content', 'is_approved'])]
class SecurityLabResource extends Model
{
    /** @use HasFactory<SecurityLabResourceFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id')->withTrashed();
    }
}
