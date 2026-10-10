<?php

namespace App\Http\Controllers\Web\Admin\Security;

use App\Domain\IAM\AuditContext;
use App\Domain\Security\Actions\RunIdorTest;
use App\Domain\Security\Enums\SecurityTest;
use App\DTOs\RunIdorTestDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Security\RunIdorTestRequest;
use App\Models\SecurityLabResource;
use App\Models\SecurityTestRun;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * HTTP side of the IDOR test only: what to show and where to go next. The
 * scenarios, the verdict and the persistence belong to RunIdorTest.
 */
class IdorTestController extends Controller
{
    private const HISTORY_PER_PAGE = 10;

    public function show(Request $request): View
    {
        $this->authorize('viewAny', SecurityTestRun::class);

        $relations = ['operator', 'actor', 'target.owner'];

        return view('admin.security.idor', [
            // Lab personas only: the users who own a synthetic resource.
            'actors' => User::query()
                ->whereIn('id', SecurityLabResource::query()->select('owner_user_id'))
                ->orderBy('name')
                ->get(),
            'resources' => SecurityLabResource::query()->with('owner')->orderBy('name')->get(),
            'runs' => SecurityTestRun::query()
                ->where('test_key', SecurityTest::Idor)
                ->with($relations)
                ->latest('created_at')
                ->latest('id')
                ->paginate(self::HISTORY_PER_PAGE),
            // The run just executed, flashed by run().
            'result' => SecurityTestRun::query()->with($relations)->find($request->session()->get('security_run')),
        ]);
    }

    public function run(RunIdorTestRequest $request): RedirectResponse
    {
        $run = RunIdorTest::execute(RunIdorTestDto::from($request), AuditContext::fromRequest($request));

        return redirect()->route('admin.security.idor.show')->with('security_run', $run->id);
    }
}
