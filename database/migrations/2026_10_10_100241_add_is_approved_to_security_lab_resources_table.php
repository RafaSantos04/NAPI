<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A property of the synthetic document that its owner knows about but
     * may not set: approval belongs to a review step. It is what the Mass
     * Assignment test tries to change (Phase 5.2).
     *
     * The type and the default are the whole integrity rule, so there is no
     * CHECK constraint.
     */
    public function up(): void
    {
        Schema::table('security_lab_resources', function (Blueprint $table) {
            $table->boolean('is_approved')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_lab_resources', function (Blueprint $table) {
            $table->dropColumn('is_approved');
        });
    }
};
