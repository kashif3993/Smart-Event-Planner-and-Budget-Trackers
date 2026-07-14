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
        if (! Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();

                $table->string('event_name', 200);

                $table->enum('event_type', [
                    'Wedding',
                    'Birthday Party',
                    'Corporate Event',
                    'Baby Shower',
                    'Graduation',
                    'Custom',
                ]);
                $table->string('custom_event_type', 100)->nullable();

                $table->date('event_date');
                $table->time('event_time')->nullable();

                $table->unsignedInteger('guest_count')->default(0);
                $table->unsignedInteger('max_guests')->nullable();

                $table->string('venue_name')->nullable();
                $table->string('location')->nullable();
                $table->string('venue_image')->nullable();

                $table->decimal('total_budget', 12, 2)->default(0);
                $table->decimal('budget_spent', 12, 2)->default(0);
                $table->enum('currency', ['PKR', 'USD'])->default('PKR');

                $table->text('description')->nullable();

                $table->enum('status', [
                    'Planning',
                    'In Progress',
                    'Completed',
                    'Cancelled',
                ])->default('Planning');

                $table->text('ai_insight')->nullable();

                $table->timestamps();
            });

            return;
        }

        // The `events` table already exists in this environment (created outside of
        // migrations). Only add the columns this rebuild introduces, so existing rows
        // are preserved instead of the table being dropped and recreated.
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'event_time')) {
                $table->time('event_time')->nullable()->after('event_date');
            }
            if (! Schema::hasColumn('events', 'max_guests')) {
                $table->unsignedInteger('max_guests')->nullable()->after('guest_count');
            }
            if (! Schema::hasColumn('events', 'venue_image')) {
                $table->string('venue_image')->nullable()->after('location');
            }
            if (! Schema::hasColumn('events', 'budget_spent')) {
                $table->decimal('budget_spent', 12, 2)->default(0)->after('total_budget');
            }
            if (! Schema::hasColumn('events', 'ai_insight')) {
                $table->text('ai_insight')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            foreach (['event_time', 'max_guests', 'venue_image', 'budget_spent', 'ai_insight'] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
