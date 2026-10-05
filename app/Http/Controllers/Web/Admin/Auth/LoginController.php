<?php

namespace App\Http\Controllers\Web\Admin\Auth;

use App\Domain\IAM\AuditContext;
use App\Events\UserLoggedIn;
use App\Events\UserLoggedOut;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Session (web guard) authentication for the admin area. Independent from
 * the API's Sanctum tokens: logging in here issues no token and logging out
 * revokes none (Phase 4.1).
 */
class LoginController extends Controller
{
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();
        event(new UserLoggedIn($user, AuditContext::fromRequest($request)));

        return redirect()->intended(route('admin.home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $context = AuditContext::fromRequest($request);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        event(new UserLoggedOut($user, 0, $context));

        return redirect()->route('admin.home');
    }
}
