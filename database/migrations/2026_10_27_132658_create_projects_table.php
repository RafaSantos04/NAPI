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
        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('created_by_membership_id');
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            // The creator is a membership of this same organization, even
            // when Eloquent is bypassed (ADR-0012).
            $table->foreign(['organization_id', 'created_by_membership_id'])
                ->references(['organization_id', 'id'])
                ->on('organization_memberships')
                ->restrictOnDelete();
            $table->unique(['organization_id', 'slug']);
            // Target of the composite foreign key of applications.
            $table->unique(['organization_id', 'id']);
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER projects_tenant_immutable
            BEFORE UPDATE ON projects
            FOR EACH ROW EXECUTE FUNCTION napi_prevent_tenant_reassignment('organization_id', 'created_by_membership_id');
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
