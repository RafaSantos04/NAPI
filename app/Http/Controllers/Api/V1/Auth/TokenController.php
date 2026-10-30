<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\IAM\Actions\IssueAccessToken;
use App\Domain\IAM\Actions\RevokeAccessToken;
use App\Domain\IAM\AuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TokenStoreRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        // API routes never read the session (RouteCoverageTest), so the
        // caller is always a personal access token: the derived one is
        // capped by it (FIND-036).
        $token = IssueAccessToken::execute(
            $user,
            $validated['name'],
            $validated['abilities'],
            now()->addDays($validated['expires_in_days'] ?? TokenStoreRequest::maxLifetimeDays()),
            AuditContext::fromRequest($request),
            $user->currentAccessToken(),
        );

        // The lifetime may come back shorter than asked (the requesting
        // token's own expiry), so the client is told the real one.
        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
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

        RevokeAccessToken::execute($user, $accessToken, AuditContext::fromRequest($request));

        return response()->json(['message' => 'Token revoked.']);
    }
}
