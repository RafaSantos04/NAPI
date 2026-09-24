<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IAM\Actions\AssignProfilesToUser;
use App\DTOs\AssignProfilesToUserDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserProfileController extends Controller
{
    public function update(AssignProfileRequest $request, User $user): JsonResponse
    {
        $dto = AssignProfilesToUserDto::from($request);
        AssignProfilesToUser::execute($user, $dto);

        return response()->json([
            'message' => 'Profiles assigned successfully.',
            'data' => UserResource::make($user->refresh()->load('profiles')),
        ]);
    }
}
