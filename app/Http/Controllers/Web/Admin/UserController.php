<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\IAM\Actions\CreateUser;
use App\Domain\IAM\Actions\DeleteUser;
use App\Domain\IAM\Actions\UpdateUser;
use App\Domain\IAM\AuditContext;
use App\DTOs\CreateUserDto;
use App\DTOs\UpdateUserDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\User\StoreUserRequest;
use App\Http\Requests\Web\Admin\User\UpdateUserRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin area adapter for users: same Policies and Actions as the API, with
 * redirects and flash messages instead of JSON (Phase 4.2). The routes add
 * the functional permission (`permission:users.*`, 404 when missing).
 */
class UserController extends Controller
{
    private const PER_PAGE = 15;

    private const MAX_SEARCH_LENGTH = 100;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $search = $request->string('q')->trim()->substr(0, self::MAX_SEARCH_LENGTH)->toString();

        $users = User::query()
            ->with('profiles')
            ->when($search !== '', function (Builder $query) use ($search) {
                // The term is matched literally: LIKE wildcards typed by the
                // user are escaped. CPF is deliberately not searchable (FIND-008).
                $term = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn (Builder $query) => $query
                    ->whereLike('name', $term)
                    ->orWhereLike('email', $term));
            })
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'search' => $search]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = CreateUser::execute(CreateUserDto::from($request), AuditContext::fromRequest($request));

        return redirect()->route('admin.users.show', $user)->with('status', 'Usuário criado.');
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        return view('admin.users.show', [
            'user' => $user->load('profiles')->loadCount('tokens'),
            'profiles' => Profile::query()->orderBy('name')->get(),
        ]);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        DeleteUser::execute($user, AuditContext::fromRequest($request));

        return redirect()->route('admin.users.index')->with('status', 'Usuário excluído.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', ['user' => $user]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        UpdateUser::execute($user, UpdateUserDto::from($request), AuditContext::fromRequest($request));

        return redirect()->route('admin.users.show', $user)->with('status', 'Usuário atualizado.');
    }
}
