<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MenuStoreRequest;
use App\Http\Requests\MenuUpdateRequest;
use App\Http\Resources\MenuResource;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MenuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('viewAny', Menu::class)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $menus = Menu::roots()->with('children')->paginate();

        return response()->json([
            'data' => MenuResource::collection($menus),
            'meta' => [
                'total' => $menus->total(),
                'per_page' => $menus->perPage(),
                'current_page' => $menus->currentPage(),
            ],
        ]);
    }

    public function show(Request $request, Menu $menu): JsonResponse|MenuResource
    {
        if (! $request->user()->can('view', $menu)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return MenuResource::make($menu->load('children'));
    }

    public function store(MenuStoreRequest $request): JsonResponse
    {
        $menu = Menu::create($request->validated());

        return response()->json(
            MenuResource::make($menu),
            201
        );
    }

    public function update(MenuUpdateRequest $request, Menu $menu): MenuResource
    {
        $menu->update($request->validated());

        return MenuResource::make($menu);
    }

    public function destroy(Menu $menu): JsonResponse
    {
        $this->authorize('delete', $menu);

        $menu->delete();

        return response()->json(['message' => 'Menu deleted.']);
    }

    /**
     * The actor's navigation: active menus they hold `{key}.view` on, at any
     * depth. A child is only reachable through a visible parent. This is a
     * projection of the functional permissions, not an access control.
     */
    public function tree(Request $request): JsonResponse
    {
        $user = $request->user();

        $visible = Menu::active()
            ->orderBy('order')
            ->get()
            ->toBase()
            ->filter(fn (Menu $menu) => $user->hasPermission($menu->permissionFor('view')))
            ->groupBy(fn (Menu $menu) => $menu->parent_id ?? '');

        return response()->json([
            'data' => MenuResource::collection($this->branch($visible, '')),
        ]);
    }

    /**
     * Each menu has a single parent, so walking down from the roots visits a
     * tree and terminates even if the data contains a cycle (FIND-019): the
     * nodes of a cycle are never reachable from a root.
     *
     * @param  Collection<array-key, Collection<int, Menu>>  $byParent
     * @return Collection<int, Menu>
     */
    private function branch(Collection $byParent, string $parentId): Collection
    {
        return $byParent->get($parentId, new Collection)
            ->each(fn (Menu $menu) => $menu->setRelation('children', $this->branch($byParent, $menu->id)))
            ->values();
    }
}
