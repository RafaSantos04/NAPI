<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\IAM\Actions\CreateProfile;
use App\Domain\IAM\Actions\DeleteProfile;
use App\Domain\IAM\Actions\UpdateProfile;
use App\Domain\IAM\AuditContext;
use App\DTOs\CreateProfileDto;
use App\DTOs\UpdateProfileDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Profile\StoreProfileRequest;
use App\Http\Requests\Web\Admin\Profile\UpdateProfileRequest;
use App\Models\Menu;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin area adapter for profiles (Phase 4.3): same Policies and Actions as
 * the API. The routes add the functional permission (`permission:profiles.*`,
 * 404 when missing). The matrix is saved by ProfilePermissionController.
 */
class ProfileController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): View
    {
        $this->authorize('viewAny', Profile::class);

        return view('admin.profiles.index', [
            'profiles' => Profile::query()
                ->withCount('users')
                ->withPermissionCount()
                ->orderBy('name')
                ->paginate(self::PER_PAGE),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Profile::class);

        return view('admin.profiles.create');
    }

    public function store(StoreProfileRequest $request): RedirectResponse
    {
        $profile = CreateProfile::execute(CreateProfileDto::from($request), AuditContext::fromRequest($request));

        return redirect()->route('admin.profiles.show', $profile)->with('status', 'Perfil criado. Defina as permissões na matriz.');
    }

    public function show(Profile $profile): View
    {
        $this->authorize('view', $profile);

        return view('admin.profiles.show', [
            'profile' => $profile->loadCount('users'),
            'menus' => Menu::hierarchy(),
            'matrix' => $profile->matrix(),
        ]);
    }

    public function edit(Profile $profile): View
    {
        $this->authorize('update', $profile);

        return view('admin.profiles.edit', ['profile' => $profile]);
    }

    public function update(UpdateProfileRequest $request, Profile $profile): RedirectResponse
    {
        UpdateProfile::execute($profile, UpdateProfileDto::from($request), AuditContext::fromRequest($request));

        return redirect()->route('admin.profiles.show', $profile)->with('status', 'Perfil atualizado.');
    }

    public function destroy(Request $request, Profile $profile): RedirectResponse
    {
        $this->authorize('delete', $profile);

        DeleteProfile::execute($profile, AuditContext::fromRequest($request));

        return redirect()->route('admin.profiles.index')->with('status', 'Perfil excluído.');
    }
}
