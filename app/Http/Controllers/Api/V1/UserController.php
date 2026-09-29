<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IAM\Actions\DeleteUser;
use App\Domain\IAM\AuditContext;
use App\Events\UserCreated;
use App\Events\UserUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Nega com 404 em vez de 403: um endpoint de leitura não deve revelar
        // que o recurso existe para quem não tem permissão de vê-lo.
        if (! $request->user()->can('viewAny', User::class)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $users = User::paginate();

        return response()->json([
            'data' => UserResource::collection($users),
            'meta' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
            ],
        ]);
    }

    public function show(Request $request, User $user): JsonResponse|UserResource
    {
        if (! $request->user()->can('view', $user)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return UserResource::make($user->load('profiles', 'details'));
    }

    public function store(UserStoreRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->validated());

            event(new UserCreated($user, AuditContext::fromRequest($request)));

            return $user;
        });

        return response()->json(
            UserResource::make($user),
            201
        );
    }

    public function update(UserUpdateRequest $request, User $user): UserResource
    {
        DB::transaction(function () use ($request, $user) {
            $user->update($request->validated());

            $changed = array_keys($user->getChanges());
            $fields = array_values(array_diff($changed, [$user->getUpdatedAtColumn()]));

            if ($fields !== []) {
                event(new UserUpdated($user, $fields, AuditContext::fromRequest($request)));
            }
        });

        return UserResource::make($user);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        DeleteUser::execute($user, AuditContext::fromRequest($request));

        return response()->json(['message' => 'User deleted.']);
    }
}
