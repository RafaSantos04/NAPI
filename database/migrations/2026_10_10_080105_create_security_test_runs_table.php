<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per execution of a Security Lab test. Append-only, like
     * audit_logs: the database sets created_at and there is no updated_at.
     *
     * What happened in a run is three separate facts: whether the runner
     * finished (execution_status), what the system answered
     * (observed_outcome) and what that means for security (security_verdict).
     */
    public function up(): void
    {
        Schema::create('security_test_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('test_key', 50);
            $table->string('scenario', 20);
            // Operator: the real, authenticated user who ran the test.
            $table->ulid('initiated_by_user_id')->nullable();
            // Actor: the identity whose authorization the test simulated.
            $table->ulid('acting_as_user_id')->nullable();
            // A real FK, not a polymorphic pair: a run can only ever point
            // at a synthetic resource.
            $table->ulid('target_resource_id')->nullable();
            $table->string('observed_outcome', 20)->nullable();
            $table->string('security_verdict', 20);
            $table->string('execution_status', 20);
            $table->jsonb('result_context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('initiated_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('acting_as_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('target_resource_id')->references('id')->on('security_lab_resources')->onDelete('set null');

            $table->index(['test_key', 'created_at']);
            $table->index('initiated_by_user_id');
            $table->index('acting_as_user_id');
            $table->index('target_resource_id');
        });

        // The Schema Builder has no CHECK constraints. The closed sets mirror
        // the enums in App\Domain\Security\Enums; the last two keep the three
        // facts consistent: nothing is observed when the runner fails, and a
        // failed run never claims a security result.
        DB::statement("ALTER TABLE security_test_runs ADD CONSTRAINT security_test_runs_scenario_check CHECK (scenario IN ('vulnerable', 'protected'))");
        DB::statement("ALTER TABLE security_test_runs ADD CONSTRAINT security_test_runs_observed_outcome_check CHECK (observed_outcome IN ('allowed', 'denied'))");
        DB::statement("ALTER TABLE security_test_runs ADD CONSTRAINT security_test_runs_security_verdict_check CHECK (security_verdict IN ('exposed', 'protected', 'inconclusive'))");
        DB::statement("ALTER TABLE security_test_runs ADD CONSTRAINT security_test_runs_execution_status_check CHECK (execution_status IN ('completed', 'error'))");
        DB::statement("ALTER TABLE security_test_runs ADD CONSTRAINT security_test_runs_outcome_matches_status_check CHECK ((execution_status = 'completed') = (observed_outcome IS NOT NULL))");
        DB::statement("ALTER TABLE security_test_runs ADD CONSTRAINT security_test_runs_error_is_inconclusive_check CHECK (execution_status = 'completed' OR security_verdict = 'inconclusive')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_test_runs');
    }
};
