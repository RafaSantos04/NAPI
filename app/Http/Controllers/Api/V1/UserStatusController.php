<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IAM\Actions\ActivateUser;
use App\Domain\IAM\Actions\DeactivateUser;
use App\Domain\IAM\AuditContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Account status as explicit operations, not a field of PUT /users/{id}:
 * deactivating revokes access and is guarded by the administrator invariant
 * (Phase 4.2). The admin area calls the same Actions.
 */
class UserStatusController extends Controller
{
    public function deactivate(Request $request, User $user): JsonResponse
    {
        $this->authorize('deactivate', $user);

        DeactivateUser::execute($user, AuditContext::fromRequest($request));

        return response()->json([
            'message' => 'User deactivated.',
            'data' => UserResource::make($user->refresh()),
        ]);
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        $this->authorize('activate', $user);

        ActivateUser::execute($user, AuditContext::fromRequest($request));

        return response()->json([
            'message' => 'User activated.',
            'data' => UserResource::make($user->refresh()),
        ]);
    }
}
