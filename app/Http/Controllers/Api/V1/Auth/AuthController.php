<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Account is inactive.',
            ]);
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $token = $user->createToken('api-token', ['read', 'write'])->plainTextToken;

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'logout',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user()->load('profiles', 'details'));
    }
}
