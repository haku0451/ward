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
        Schema::create('attack_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hostname')->nullable();
            $table->string('client_ip', 45);
            $table->string('method', 16);
            $table->string('path', 2048);
            $table->string('rule_id', 64)->nullable();
            $table->string('rule_message', 1024)->nullable();
            $table->string('action', 32);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['node_id', 'event_id']);
            $table->index(['occurred_at', 'id']);
            $table->index(['node_id', 'occurred_at']);
            $table->index(['client_ip', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
            $table->index(['site_id', 'occurred_at']);
            $table->index('rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attack_events');
    }
};
