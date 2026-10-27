<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('environments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('application_id');
            $table->ulid('responsible_membership_id');
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('classification', 20);
            // Human, auditable record of what may be tested; not an
            // executable allowlist (egress and SSRF rules belong to P10).
            $table->text('authorized_scope');
            $table->string('authorization_reference', 255)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            // Parent application and responsible membership belong to this
            // same organization, even when Eloquent is bypassed (ADR-0012).
            $table->foreign(['organization_id', 'application_id'])
                ->references(['organization_id', 'id'])
                ->on('applications')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'responsible_membership_id'])
                ->references(['organization_id', 'id'])
                ->on('organization_memberships')
                ->restrictOnDelete();
            // Slug unique within the application; also indexes the foreign key.
            $table->unique(['organization_id', 'application_id', 'slug']);
        });

        DB::statement("ALTER TABLE environments ADD CONSTRAINT environments_classification_check CHECK (classification IN ('production', 'staging', 'development', 'other'))");

        // The responsible membership may change; tenant and parent may not.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER environments_tenant_immutable
            BEFORE UPDATE ON environments
            FOR EACH ROW EXECUTE FUNCTION napi_prevent_tenant_reassignment('organization_id', 'application_id');
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('environments');
    }
};
