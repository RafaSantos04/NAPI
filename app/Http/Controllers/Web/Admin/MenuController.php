<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\IAM\Actions\CreateMenu;
use App\Domain\IAM\Actions\DeleteMenu;
use App\Domain\IAM\Actions\UpdateMenu;
use App\Domain\IAM\AuditContext;
use App\DTOs\CreateMenuDto;
use App\DTOs\UpdateMenuDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Menu\StoreMenuRequest;
use App\Http\Requests\Web\Admin\Menu\UpdateMenuRequest;
use App\Models\Menu;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin area adapter for menus (Phase 4.4): same Policies, request rules
 * (immutable key, no cycles) and Actions as the API.
 *
 * The list is the whole hierarchy, not a page of it: a submenu only reads
 * well under its parent, and menus are a navigation structure of a few
 * dozen rows, loaded in one query.
 */
class MenuController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Menu::class);

        return view('admin.menus.index', ['menus' => Menu::hierarchy()]);
    }

    public function create(): View
    {
        $this->authorize('create', Menu::class);

        return view('admin.menus.create', ['parents' => Menu::hierarchy()]);
    }

    public function store(StoreMenuRequest $request): RedirectResponse
    {
        $menu = CreateMenu::execute(CreateMenuDto::from($request), AuditContext::fromRequest($request));

        return redirect()->route('admin.menus.show', $menu)->with('status', 'Menu criado.');
    }

    public function show(Menu $menu): View
    {
        $this->authorize('view', $menu);

        return view('admin.menus.show', [
            'menu' => $menu->load(['parent', 'children']),
            'grantingProfiles' => $menu->grantingProfilesCount(),
        ]);
    }

    public function edit(Menu $menu): View
    {
        $this->authorize('update', $menu);

        return view('admin.menus.edit', [
            'menu' => $menu,
            // The parent picker leaves out the menu and its whole branch.
            'parents' => Menu::hierarchy($menu),
            // For the profiles panel: each profile's matrix, this menu's row
            // included.
            'profiles' => Profile::query()->with('menus')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateMenuRequest $request, Menu $menu): RedirectResponse
    {
        UpdateMenu::execute($menu, UpdateMenuDto::from($request), AuditContext::fromRequest($request));

        return redirect()->route('admin.menus.show', $menu)->with('status', 'Menu atualizado.');
    }

    public function destroy(Request $request, Menu $menu): RedirectResponse
    {
        $this->authorize('delete', $menu);

        DeleteMenu::execute($menu, AuditContext::fromRequest($request));

        return redirect()->route('admin.menus.index')->with('status', 'Menu excluído.');
    }
}
