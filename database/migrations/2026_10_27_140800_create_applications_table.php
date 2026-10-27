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
        Schema::create('applications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('project_id');
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            // The parent project belongs to this same organization, even when
            // Eloquent is bypassed (ADR-0012). The project's own foreign key
            // already guarantees the organization exists.
            $table->foreign(['organization_id', 'project_id'])
                ->references(['organization_id', 'id'])
                ->on('projects')
                ->restrictOnDelete();
            // Slug unique within the project; also indexes the foreign key.
            $table->unique(['organization_id', 'project_id', 'slug']);
            // Target of the composite foreign key of environments.
            $table->unique(['organization_id', 'id']);
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER applications_tenant_immutable
            BEFORE UPDATE ON applications
            FOR EACH ROW EXECUTE FUNCTION napi_prevent_tenant_reassignment('organization_id', 'project_id');
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
