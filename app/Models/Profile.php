<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'description', 'is_system'])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this, UserProfile>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_profiles')
            ->using(UserProfile::class)
            ->withPivot('assigned_by', 'assigned_at');
    }

    /**
     * @return BelongsToMany<Menu, $this, MenuProfile>
     */
    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'menu_profiles')
            ->using(MenuProfile::class)
            ->withPivot('can_view', 'can_create', 'can_update', 'can_delete');
    }
}
