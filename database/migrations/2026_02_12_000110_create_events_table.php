<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->foreignId('to_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('name');
            $table->string('event_type_id')->index();
            $table->string('type')->default('webhook');
            $table->string('subscription_type')->nullable()->index();
            $table->string('method_name')->nullable();
            $table->string('endpoint_api')->nullable();
            $table->json('payload_mapping')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->index(['event_type_id', 'platform_id']);
            $table->index(['type', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
