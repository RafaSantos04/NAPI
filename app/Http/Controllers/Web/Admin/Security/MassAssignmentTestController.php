<?php

namespace App\Http\Controllers\Web\Admin\Security;

use App\Domain\IAM\AuditContext;
use App\Domain\Security\Actions\RunMassAssignmentTest;
use App\Domain\Security\Enums\SecurityTest;
use App\DTOs\RunMassAssignmentTestDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\Security\RunMassAssignmentTestRequest;
use App\Models\SecurityLabResource;
use App\Models\SecurityTestRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * HTTP side of the Mass Assignment test only: what to show and where to go
 * next. The scenarios, the verdict and the persistence belong to
 * RunMassAssignmentTest.
 */
class MassAssignmentTestController extends Controller
{
    private const HISTORY_PER_PAGE = 10;

    public function show(Request $request): View
    {
        $this->authorize('viewAny', SecurityTestRun::class);

        // Runs of this test only: neither the history nor the result panel
        // shows an execution of another one.
        $runs = SecurityTestRun::query()
            ->where('test_key', SecurityTest::MassAssignment)
            ->with(['operator', 'actor', 'target']);

        // The run just executed, flashed by run(), or else the one picked in
        // the history (?run=). An id of a run, never of a lab resource.
        $selected = $request->session()->get('security_run') ?? $request->query('run');

        return view('admin.security.mass-assignment', [
            'resources' => SecurityLabResource::query()->with('owner')->orderBy('name')->get(),
            'runs' => $runs->clone()
                ->latest('created_at')
                ->latest('id')
                ->paginate(self::HISTORY_PER_PAGE)
                ->withQueryString(),
            // Anything that is not the id of a run of this test shows nothing.
            'result' => is_string($selected) ? $runs->find($selected) : null,
        ]);
    }

    public function run(RunMassAssignmentTestRequest $request): RedirectResponse
    {
        $run = RunMassAssignmentTest::execute(RunMassAssignmentTestDto::from($request), AuditContext::fromRequest($request));

        return redirect()->route('admin.security.mass-assignment.show')->with('security_run', $run->id);
    }
}
