<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trebel_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('priority')->default(100);
            $table->string('trigger_property');
            $table->string('trigger_value')->nullable();
            $table->json('conditions')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_rules');
    }
};
