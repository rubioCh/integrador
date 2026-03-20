<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_http_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->string('method', 10)->default('POST');
            $table->string('base_url')->nullable();
            $table->string('path')->nullable();
            $table->json('headers_json')->nullable();
            $table->json('query_json')->nullable();
            $table->string('auth_mode', 64)->nullable();
            $table->json('auth_config_json')->nullable();
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->json('retry_policy_json')->nullable();
            $table->json('idempotency_config_json')->nullable();
            $table->json('allowlist_domains_json')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('auth_mode');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_http_configs');
    }
};
