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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id');

            $table->string('task_name', 255);

            $table->enum('phase', [
                'Pre-Planning',
                'Preparation',
                'Day-Of'
            ]);

            $table->date('due_date')->nullable();

            $table->enum('priority', ['Low', 'Medium', 'High'])->default('Medium');

            $table->foreignId('dependency_task_id')->nullable()->constrained('tasks')->nullOnDelete();

            $table->enum('source', ['AI', 'Manual'])->default('AI');

            $table->enum('status', ['Pending', 'Completed', 'Skipped'])->default('Pending');

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
