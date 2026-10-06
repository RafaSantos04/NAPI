<?php

namespace App\Http\Middleware;

use App\Http\Admin\AdminNavigation;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The web session exists only for the admin area. A signed-in user who
 * cannot use any part of it (e.g. lost their profile after logging in) loses
 * the session, the same way EnsureUserIsActive treats a deactivated one, and
 * lands where a guest would. Guests pass through: `auth` handles them.
 *
 * Only answers "may enter the admin area"; what each page allows stays with
 * the route permission and the Policies.
 */
class EnsureUserCanAccessAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user instanceof User && ! AdminNavigation::allows($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.home')
                ->withErrors(['email' => 'Esta conta não tem acesso à área administrativa.']);
        }

        return $next($request);
    }
}
