<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per model call, successful or not (O4). Never stores the recipe text.
     */
    public function up(): void
    {
        Schema::create('ai_call_logs', function (Blueprint $table) {
            $table->id();
            // Kept for cost totals after account deletion.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature');
            $table->string('provider');
            $table->string('model');
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            // Billed cost reported by OpenRouter, null when the provider did not report it.
            $table->decimal('cost_usd', 12, 8)->nullable();
            $table->unsignedInteger('duration_ms');
            $table->string('outcome');
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_call_logs');
    }
};
