<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Guests get the black landing with the login panel; signed-in users
     * (already cleared by `admin.access`) get the shell.
     */
    public function __invoke(Request $request): View
    {
        return view($request->user() ? 'admin.home' : 'admin.index');
    }
}
