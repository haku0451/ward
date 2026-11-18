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
        Schema::create('policy_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('hash', 64);
            $table->json('payload');
            $table->string('crs_version');
            $table->string('crs_source');
            $table->string('waf_mode', 32);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_revisions');
    }
};
