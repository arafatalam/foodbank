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
        Schema::table('stock_entries', function (Blueprint $table) {
            // Link the household after notes; other entry types leave it empty.
            $table->foreignId('household_id')
                ->nullable()
                ->after('notes')
                ->constrained('households')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_entries', function (Blueprint $table) {
            // Remove the household connection when undoing this migration.
            $table->dropConstrainedForeignId('household_id');
        });
    }
};
