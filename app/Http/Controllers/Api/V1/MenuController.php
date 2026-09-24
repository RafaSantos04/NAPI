<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MenuStoreRequest;
use App\Http\Requests\MenuUpdateRequest;
use App\Http\Resources\MenuResource;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function tree(Request $request): JsonResponse
    {
        $user = $request->user();

        $profileIds = $user->profiles()->pluck('profiles.id');

        $menus = Menu::whereHas('profiles', function ($query) use ($profileIds) {
            $query->whereIn('menu_profiles.profile_id', $profileIds)
                ->where('menu_profiles.can_view', true);
        })
            ->whereNull('parent_id')
            ->orderBy('order')
            ->with(['children' => fn ($q) => $q->orderBy('order')])
            ->get();

        return response()->json([
            'data' => MenuResource::collection($menus),
        ]);
    }
}
