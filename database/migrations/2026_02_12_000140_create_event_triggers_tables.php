<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_trigger_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->string('operator')->default('and');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('event_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_trigger_group_id')->nullable()
                ->constrained('event_trigger_groups')
                ->nullOnDelete();
            $table->string('field');
            $table->string('operator');
            $table->json('value')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('event_trigger_group_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_trigger_group_id')->constrained('event_trigger_groups')->cascadeOnDelete();
            $table->string('field');
            $table->string('operator');
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_trigger_group_conditions');
        Schema::dropIfExists('event_triggers');
        Schema::dropIfExists('event_trigger_groups');
    }
};
