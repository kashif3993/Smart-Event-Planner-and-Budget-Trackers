<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contention_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('strategy', ['Strict Hierarchy', 'Multi-Agent Negotiation']);
            $table->decimal('pooled_budget_cap', 12, 2);
            $table->decimal('global_deficit', 12, 2);
            $table->boolean('ai_used')->default(false);
            $table->string('fallback_reason')->nullable();

            $table->json('participating_event_ids');
            $table->json('concessions');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contention_resolutions');
    }
};
