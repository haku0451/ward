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
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('waf_mode', 32)->nullable();
        });

        Schema::table('ip_blocks', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('node_id')->constrained()->nullOnDelete();
            $table->text('revoke_reason')->nullable();
        });

        Schema::table('policy_revisions', function (Blueprint $table) {
            $table->text('signature')->nullable();
            $table->string('public_key', 128)->nullable();
            $table->foreignId('rolled_back_from_id')->nullable()->constrained('policy_revisions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('policy_revisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rolled_back_from_id');
            $table->dropColumn(['signature', 'public_key']);
        });

        Schema::table('ip_blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
            $table->dropColumn('revoke_reason');
        });

        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('waf_mode');
        });
    }
};
