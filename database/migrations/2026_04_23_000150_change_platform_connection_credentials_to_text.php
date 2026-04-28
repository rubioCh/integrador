<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_connections', function (Blueprint $table) {
            $table->longText('credentials')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE platform_connections MODIFY credentials JSON NULL');
    }
};
