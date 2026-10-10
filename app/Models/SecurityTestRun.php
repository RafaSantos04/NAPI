<?php

namespace App\Models;

use App\Domain\Security\Enums\SecurityTest;
use App\Domain\Security\Enums\SecurityTestExecutionStatus;
use App\Domain\Security\Enums\SecurityTestObservedOutcome;
use App\Domain\Security\Enums\SecurityTestScenario;
use App\Domain\Security\Enums\SecurityTestVerdict;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What happened in one execution of a Security Lab test. Not the audit
 * trail: audit_logs records that an operator ran a test, this records what
 * the test observed.
 */
#[Fillable([
    'test_key',
    'scenario',
    'initiated_by_user_id',
    'acting_as_user_id',
    'target_resource_id',
    'observed_outcome',
    'security_verdict',
    'execution_status',
    'result_context',
])]
class SecurityTestRun extends Model
{
    use HasUlids;

    // Append-only: created_at is set by the database, as in audit_logs.
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'test_key' => SecurityTest::class,
            'scenario' => SecurityTestScenario::class,
            'observed_outcome' => SecurityTestObservedOutcome::class,
            'security_verdict' => SecurityTestVerdict::class,
            'execution_status' => SecurityTestExecutionStatus::class,
            'result_context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The real, authenticated user who ran the test.
     *
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id')->withTrashed();
    }

    /**
     * The identity whose authorization the test simulated.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acting_as_user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<SecurityLabResource, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(SecurityLabResource::class, 'target_resource_id');
    }
}
