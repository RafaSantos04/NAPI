<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
     * The matrix as stored, in the shape SyncMenusRequest takes: menu id =>
     * the four flags. Lets the admin page ask ProfilePolicy::syncMenus
     * whether the current matrix could be saved by the actor at all.
     *
     * @return array<string, array<string, bool>>
     */
    public function matrix(): array
    {
        return $this->menus->mapWithKeys(fn (Menu $menu) => [
            $menu->id => collect(MenuProfile::ACTIONS)
                ->mapWithKeys(fn (string $action) => ["can_{$action}" => $menu->pivot?->grants($action) ?? false])
                ->all(),
        ])->all();
    }

    /**
     * The matrix with one menu's row replaced by $flags, as SyncMenusRequest
     * would receive it. A row that grants nothing is dropped: that is what
     * unlinking the menu from this profile means.
     *
     * @param  array<mixed>  $flags  can_* flags as sent, not yet validated.
     * @return array<string, array<string, bool>>
     */
    public function matrixWith(Menu $menu, array $flags): array
    {
        $matrix = $this->matrix();
        $row = collect(MenuProfile::ACTIONS)
            ->mapWithKeys(fn (string $action) => ["can_{$action}" => filter_var($flags["can_{$action}"] ?? false, FILTER_VALIDATE_BOOLEAN)])
            ->all();

        if (in_array(true, $row, true)) {
            $matrix[$menu->id] = $row;
        } else {
            unset($matrix[$menu->id]);
        }

        return $matrix;
    }

    /**
     * Adds `permissions_count`: how many flags the matrix grants, in the same
     * query as the profiles. The admin profile's implicit permissions are
     * not rows, so they are not counted.
     *
     * @param  Builder<Profile>  $query
     * @return Builder<Profile>
     */
    public function scopeWithPermissionCount(Builder $query): Builder
    {
        return $query->addSelect(['permissions_count' => MenuProfile::query()
            ->selectRaw('coalesce(sum(can_view::int + can_create::int + can_update::int + can_delete::int), 0)')
            ->whereColumn('profile_id', 'profiles.id'),
        ]);
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
