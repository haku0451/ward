<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('waf_settings', function (Blueprint $table) {
            $table->id();
            $table->string('waf_mode', 32)->default('detection_only');
            $table->boolean('auto_block_enabled')->default(false);
            $table->unsignedInteger('auto_block_threshold')->default(20);
            $table->unsignedInteger('auto_block_window_seconds')->default(300);
            $table->unsignedInteger('auto_block_duration_seconds')->default(3600);
            $table->timestamps();
        });

        DB::table('waf_settings')->insert([
            'waf_mode' => 'detection_only',
            'auto_block_enabled' => false,
            'auto_block_threshold' => 20,
            'auto_block_window_seconds' => 300,
            'auto_block_duration_seconds' => 3600,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waf_settings');
    }
};
