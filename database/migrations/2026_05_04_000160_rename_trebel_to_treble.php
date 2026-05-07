<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trebel_templates') && ! Schema::hasTable('treble_templates')) {
            Schema::rename('trebel_templates', 'treble_templates');
        }

        if (Schema::hasTable('message_rules') && Schema::hasColumn('message_rules', 'trebel_template_id')) {
            Schema::table('message_rules', function (Blueprint $table) {
                $table->renameColumn('trebel_template_id', 'treble_template_id');
            });
        }

        DB::table('platform_connections')
            ->where('platform_type', 'trebel')
            ->update([
                'platform_type' => 'treble',
                'slug' => DB::raw("CASE WHEN slug = 'trebel' THEN 'treble' ELSE slug END"),
                'name' => DB::raw("CASE WHEN name = 'Trebel' THEN 'Treble' ELSE name END"),
            ]);
    }

    public function down(): void
    {
        DB::table('platform_connections')
            ->where('platform_type', 'treble')
            ->update([
                'platform_type' => 'trebel',
                'slug' => DB::raw("CASE WHEN slug = 'treble' THEN 'trebel' ELSE slug END"),
                'name' => DB::raw("CASE WHEN name = 'Treble' THEN 'Trebel' ELSE name END"),
            ]);

        if (Schema::hasTable('message_rules') && Schema::hasColumn('message_rules', 'treble_template_id')) {
            Schema::table('message_rules', function (Blueprint $table) {
                $table->renameColumn('treble_template_id', 'trebel_template_id');
            });
        }

        if (Schema::hasTable('treble_templates') && ! Schema::hasTable('trebel_templates')) {
            Schema::rename('treble_templates', 'trebel_templates');
        }
    }
};
