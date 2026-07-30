<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dismissed_guest_duplicate_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id_a')->constrained('guests')->cascadeOnDelete();
            $table->foreignId('guest_id_b')->constrained('guests')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_group_id', 'guest_id_a', 'guest_id_b'], 'dismissed_guest_dup_pairs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dismissed_guest_duplicate_pairs');
    }
};
