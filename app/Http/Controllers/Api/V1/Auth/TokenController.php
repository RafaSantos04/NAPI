<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\IAM\AuditContext;
use App\Events\TokenCreated;
use App\Events\TokenRevoked;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TokenStoreRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

class TokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->get();

        return response()->json([
            'data' => $tokens->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'created_at' => $token->created_at,
                'expires_at' => $token->expires_at,
            ]),
        ]);
    }

    public function store(TokenStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $token = DB::transaction(function () use ($user, $validated, $request): NewAccessToken {
            $token = $user->createToken(
                $validated['name'],
                $validated['abilities'],
                now()->addDays($validated['expires_in_days'] ?? TokenStoreRequest::maxLifetimeDays())
            );

            event(new TokenCreated($user, $token->accessToken, AuditContext::fromRequest($request)));

            return $token;
        });

        return response()->json([
            'token' => $token->plainTextToken,
            'message' => 'Token created successfully. Store it safely.',
        ], 201);
    }

    public function destroy(Request $request, int $token): JsonResponse
    {
        $user = $request->user();
        $accessToken = $user->tokens()->whereKey($token)->first();

        // Only the caller's own tokens exist from their point of view.
        if (! $accessToken) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        DB::transaction(function () use ($user, $accessToken, $request) {
            $accessToken->delete();

            event(new TokenRevoked($user, $accessToken, AuditContext::fromRequest($request)));
        });

        return response()->json(['message' => 'Token revoked.']);
    }
}
