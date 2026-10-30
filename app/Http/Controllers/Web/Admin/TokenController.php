<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\IAM\Actions\IssueAccessToken;
use App\Domain\IAM\Actions\RevokeAccessToken;
use App\Domain\IAM\AuditContext;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTokenAbility;
use App\Http\Requests\Web\Admin\Token\StoreTokenRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The signed-in user's own API tokens. For a privileged account this is the
 * only way to get one (ADR-0013, item 13); the API refuses it. Same Actions
 * and audit events as the API's TokenController.
 */
class TokenController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request): View
    {
        $tokens = $request->user()->tokens()->latest()->paginate(self::PER_PAGE);

        return view('admin.tokens.index', ['tokens' => $tokens]);
    }

    public function create(): View
    {
        return view('admin.tokens.create', [
            'abilities' => EnsureTokenAbility::ABILITIES,
            'lifetimes' => StoreTokenRequest::LIFETIME_HOURS,
            'defaultLifetime' => StoreTokenRequest::DEFAULT_LIFETIME_HOURS,
        ]);
    }

    public function store(StoreTokenRequest $request): Response
    {
        $validated = $request->validated();

        $token = IssueAccessToken::execute(
            $request->user(),
            $validated['name'],
            $validated['abilities'],
            now()->addHours((int) $validated['lifetime_hours']),
            AuditContext::fromRequest($request),
        );

        // Shown once, straight from this response: a redirect with flash
        // would store the plain-text token in the sessions table.
        return response()
            ->view('admin.tokens.created', ['token' => $token], 201)
            ->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $user = $request->user();

        // Only the user's own tokens exist from their point of view.
        $accessToken = $user->tokens()->whereKey($token)->firstOrFail();

        RevokeAccessToken::execute($user, $accessToken, AuditContext::fromRequest($request));

        return redirect()->route('admin.tokens.index')->with('status', 'Token revogado.');
    }
}
