<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('category_name', 150);
            $table->decimal('suggested_percentage', 5, 2)->default(0);
            $table->decimal('allocated_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->unsignedTinyInteger('ai_slash_priority')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_categories');
    }
};
