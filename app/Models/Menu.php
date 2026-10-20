<?php

namespace App\Models;

use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property-read MenuProfile|null $pivot
 */
#[Fillable(['parent_id', 'key', 'label', 'route_name', 'icon', 'order', 'is_active', 'description'])]
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    /**
     * @return HasMany<Menu, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('order');
    }

    /**
     * @return BelongsToMany<Profile, $this, MenuProfile>
     */
    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'menu_profiles')
            ->using(MenuProfile::class)
            ->withPivot('can_view', 'can_create', 'can_update', 'can_delete');
    }

    /**
     * Functional permission identifier for an action on this menu's area,
     * e.g. "users.update". Built from the immutable `key`, never from the
     * editable `route_name` (ADR-0008).
     */
    public function permissionFor(string $action): string
    {
        return "{$this->key}.{$action}";
    }

    /**
     * How many profiles grant at least one action on this menu's key. Their
     * rows go away with the menu, so the admin area says it before a delete.
     */
    public function grantingProfilesCount(): int
    {
        return MenuProfile::query()
            ->where('menu_id', $this->id)
            ->where(fn (Builder $query) => $query
                ->where('can_view', true)
                ->orWhere('can_create', true)
                ->orWhere('can_update', true)
                ->orWhere('can_delete', true))
            ->count();
    }

    /**
     * Whether $menuId is this menu or sits anywhere below it, i.e. whether
     * making it this menu's parent would close a cycle (FIND-019). Walks up
     * from $menuId in one recursive query; UNION, not UNION ALL, keeps the
     * walk finite even over a cycle already in the data.
     */
    public function isSelfOrAncestorOf(string $menuId): bool
    {
        $result = DB::selectOne(<<<'SQL'
            WITH RECURSIVE lineage (id, parent_id) AS (
                SELECT id, parent_id FROM menus WHERE id = ?
                UNION
                SELECT menus.id, menus.parent_id FROM menus JOIN lineage ON menus.id = lineage.parent_id
            )
            SELECT EXISTS (SELECT 1 FROM lineage WHERE id = ?) AS found
            SQL, [$menuId, $this->id]);

        return (bool) $result->found;
    }

    /**
     * Every menu in navigation order, with its depth: each root by `order`,
     * followed by its submenus. One query; each menu also gets its `parent`
     * relation from the same set, so a list can show it without N+1.
     *
     * $without leaves that menu and everything below it out, which is what
     * a "parent" picker for it must offer. Without it, menus no root reaches
     * (only possible through a cycle stored before FIND-019 was fixed) come
     * last, at depth 0, so they can still be found and repaired.
     *
     * @return list<array{menu: Menu, depth: int}>
     */
    public static function hierarchy(?Menu $without = null): array
    {
        $menus = static::query()->orderBy('order')->orderBy('label')->get();
        $byId = $menus->keyBy('id');
        $byParent = $menus->groupBy(fn (Menu $menu) => $menu->parent_id ?? '');

        $menus->each(fn (Menu $menu) => $menu->setRelation('parent', $byId->get($menu->parent_id ?? '')));

        $ordered = [];
        $visit = function (string $parentId, int $depth) use (&$visit, &$ordered, $byParent, $without): void {
            foreach ($byParent->get($parentId, []) as $menu) {
                if ($without?->is($menu)) {
                    continue;
                }

                $ordered[$menu->id] = ['menu' => $menu, 'depth' => $depth];
                $visit($menu->id, $depth + 1);
            }
        };
        $visit('', 0);

        if ($without === null) {
            foreach ($menus as $menu) {
                $ordered[$menu->id] ??= ['menu' => $menu, 'depth' => 0];
            }
        }

        return array_values($ordered);
    }

    /**
     * @param  Builder<Menu>  $query
     * @return Builder<Menu>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Menu>  $query
     * @return Builder<Menu>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id')->orderBy('order');
    }
}
