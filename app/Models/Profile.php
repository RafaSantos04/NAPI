<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

#[Fillable(['name', 'slug', 'description', 'is_system'])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory, HasUlids;

    /**
     * Slug of the system profile that holds every functional permission and
     * whose membership is protected by the last-active-administrator invariant.
     */
    public const ADMIN = 'admin';

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

    public function isAdministrator(): bool
    {
        return $this->slug === self::ADMIN;
    }

    /**
     * Functional permissions granted by this profile's matrix rows, as
     * "{menu key}.{action}".
     *
     * @return Collection<int, string>
     */
    public function permissionKeys(): Collection
    {
        return $this->menus->toBase()->flatMap(
            fn (Menu $menu) => collect(MenuProfile::ACTIONS)
                ->filter(fn (string $action) => $menu->pivot?->grants($action) ?? false)
                ->map(fn (string $action) => $menu->permissionFor($action))
        )->values();
    }
}
