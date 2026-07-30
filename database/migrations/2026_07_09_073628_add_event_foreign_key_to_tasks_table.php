<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->foreignKeyExists()) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! $this->foreignKeyExists()) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
        });
    }

    protected function foreignKeyExists(): bool
    {
        // information_schema.TABLE_CONSTRAINTS is MySQL-specific — this guard only
        // matters for the MySQL environment it was written for (where the FK may
        // already exist outside migrations). Other drivers (e.g. sqlite in tests)
        // never have it pre-exist, so it's safe to say so and let the migration proceed.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'tasks')
            ->where('CONSTRAINT_NAME', 'tasks_event_id_foreign')
            ->exists();
    }
};
