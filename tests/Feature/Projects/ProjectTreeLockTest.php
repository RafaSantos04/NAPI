<?php

use App\Domain\Projects\ProjectTreeLock;
use App\Models\Application;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Outside a transaction a shared lock is released at once, so the ancestor
// check would protect nothing; the lock refuses to run there.
it('refuses to lock the project tree outside a transaction', function () {
    $application = (new Application)->forceFill(['project_id' => (string) Str::ulid()]);

    // RefreshDatabase wraps each test in a transaction; leave it for the call.
    DB::rollBack();

    try {
        expect(fn () => ProjectTreeLock::activeApplication(new Organization, $application))
            ->toThrow(LogicException::class, 'The project tree must be locked inside a transaction.');
    } finally {
        DB::beginTransaction();
    }
});
