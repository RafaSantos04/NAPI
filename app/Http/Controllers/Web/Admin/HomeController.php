<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Entry point of the admin area. Open to guests: it renders the empty shell
 * with the login panel, or the signed-in state.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.index');
    }
}
