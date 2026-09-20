<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_details', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Dados pessoais
            $table->string('cpf_hash')->unique()->nullable();
            $table->timestamp('cpf_verified_at')->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestamp('phone_verified_at')->nullable();

            // Endereço
            $table->string('cep', 8)->nullable();
            $table->string('street')->nullable();
            $table->string('number', 10)->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 2)->nullable();
            $table->string('country', 2)->default('BR');

            // LGPD
            $table->timestamp('data_consent_at')->nullable();
            $table->timestamp('data_export_requested_at')->nullable();
            $table->timestamp('data_deletion_requested_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'cpf_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_details');
    }
};
