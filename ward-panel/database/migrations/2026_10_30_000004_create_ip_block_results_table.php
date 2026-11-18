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
        Schema::create('ip_block_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ip_block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('message')->nullable();
            $table->unsignedBigInteger('config_version')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->unique(['ip_block_id', 'node_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_block_results');
    }
};
