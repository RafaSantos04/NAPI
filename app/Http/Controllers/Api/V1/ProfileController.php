<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IAM\Actions\SyncMenuPermissions;
use App\DTOs\SyncMenuPermissionsDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileStoreRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\SyncMenusRequest;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Nega com 404 em vez de 403: mesmo padrão do UserController.
        if (! $request->user()->can('viewAny', Profile::class)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $profiles = Profile::paginate();

        return response()->json([
            'data' => ProfileResource::collection($profiles),
            'meta' => [
                'total' => $profiles->total(),
                'per_page' => $profiles->perPage(),
                'current_page' => $profiles->currentPage(),
            ],
        ]);
    }

    public function show(Request $request, Profile $profile): JsonResponse|ProfileResource
    {
        if (! $request->user()->can('view', $profile)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return ProfileResource::make($profile->load('menus'));
    }

    public function store(ProfileStoreRequest $request): JsonResponse
    {
        $profile = Profile::create($request->validated());

        return response()->json(
            ProfileResource::make($profile),
            201
        );
    }

    public function update(ProfileUpdateRequest $request, Profile $profile): ProfileResource
    {
        $profile->update($request->validated());

        return ProfileResource::make($profile);
    }

    public function destroy(Profile $profile): JsonResponse
    {
        $this->authorize('delete', $profile);

        $profile->delete();

        return response()->json(['message' => 'Profile deleted.']);
    }

    public function syncMenus(SyncMenusRequest $request, Profile $profile): JsonResponse
    {
        $dto = SyncMenuPermissionsDto::from($request);
        SyncMenuPermissions::execute($profile, $dto);

        return response()->json([
            'message' => 'Menu permissions synced.',
            'data' => ProfileResource::make($profile->load('menus')),
        ]);
    }
}
