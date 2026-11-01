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
        Schema::create('api_specifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('application_id');
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            // The parent application belongs to this same organization, even
            // when Eloquent is bypassed (ADR-0012, ADR-0014).
            $table->foreign(['organization_id', 'application_id'])
                ->references(['organization_id', 'id'])
                ->on('applications')
                ->restrictOnDelete();
            // Slug unique within the application; also indexes the foreign key.
            $table->unique(['organization_id', 'application_id', 'slug']);
            // Target of the composite foreign key of the versions.
            $table->unique(['organization_id', 'id']);
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER api_specifications_tenant_immutable
            BEFORE UPDATE ON api_specifications
            FOR EACH ROW EXECUTE FUNCTION napi_prevent_tenant_reassignment('organization_id', 'application_id');
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_specifications');
    }
};
