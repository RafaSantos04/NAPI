<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\IAM\Actions\VerifyLoginCredentials;
use App\Domain\IAM\AuditContext;
use App\Events\UserLoggedIn;
use App\Events\UserLoggedOut;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTokenAbility;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        // Same budget as the admin form (ADR-0013); an inactive account gets
        // the same answer as a wrong password (FIND-018).
        $user = VerifyLoginCredentials::execute(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request,
        );

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials.',
            ]);
        }

        // A login token carries every ability: its owner's IAM permissions
        // are the real limit. Narrower tokens are minted via POST /tokens.
        $token = DB::transaction(function () use ($user, $request) {
            event(new UserLoggedIn($user, new AuditContext($user->id, $request->ip(), $request->userAgent())));

            return $user->createToken('api-token', EnsureTokenAbility::ABILITIES)->plainTextToken;
        });

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $request) {
            $revoked = $user->tokens()->delete();

            event(new UserLoggedOut($user, $revoked, AuditContext::fromRequest($request)));
        });

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user()->load('profiles', 'details'));
    }
}
