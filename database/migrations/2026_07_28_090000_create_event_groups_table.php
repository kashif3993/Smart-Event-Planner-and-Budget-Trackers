<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name', 200);
            $table->text('description')->nullable();

            $table->enum('group_type', [
                'Wedding',
                'Birthday Party',
                'Corporate Event',
                'Baby Shower',
                'Graduation',
                'Custom',
            ]);
            $table->string('custom_group_type', 100)->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->enum('currency', ['PKR', 'USD'])->nullable();

            $table->enum('budget_mode', ['Distributed', 'Pooled'])->default('Distributed');
            $table->decimal('pooled_budget_cap', 12, 2)->nullable();

            $table->enum('status', ['Active', 'Archived'])->default('Active');
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_groups');
    }
};
