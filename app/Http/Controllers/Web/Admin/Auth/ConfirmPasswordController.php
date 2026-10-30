<?php

namespace App\Http\Controllers\Web\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Auth\ConfirmPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Target of Laravel's password.confirm middleware in the admin area. A
 * correct password marks the session as recently confirmed and returns to
 * the page that asked for it.
 */
class ConfirmPasswordController extends Controller
{
    public function show(): View
    {
        return view('admin.auth.confirm-password');
    }

    public function store(ConfirmPasswordRequest $request): RedirectResponse
    {
        $request->confirm();
        $request->session()->passwordConfirmed();

        return redirect()->intended(route('admin.tokens.index'));
    }
}
