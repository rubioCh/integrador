<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('schedule_expression')->nullable()->after('type');
            $table->timestamp('last_executed_at')->nullable()->after('schedule_expression');
            $table->longText('command_sql')->nullable()->after('last_executed_at');
            $table->boolean('enable_update_hubdb')->default(false)->after('command_sql');
            $table->integer('hubdb_table_id')->nullable()->after('enable_update_hubdb');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'schedule_expression',
                'last_executed_at',
                'command_sql',
                'enable_update_hubdb',
                'hubdb_table_id',
            ]);
        });
    }
};
