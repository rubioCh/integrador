<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('record_id')->nullable()->constrained('records')->nullOnDelete();
            $table->string('event_type')->index();
            $table->string('status')->default('init')->index();
            $table->json('payload')->nullable();
            $table->text('message')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['record_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};
