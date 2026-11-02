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
        Schema::create('stock_movements', function (Blueprint $table) {

            $table->id();

            // Connect this movement to its receipt and preserve that history.
            $table->foreignId('stock_entry_id')
                ->constrained()
                ->restrictOnDelete();

            // Identify the stock item and prevent deleting it once history exists.
            $table->foreignId('stock_item_id')
                ->constrained()
                ->restrictOnDelete();

            // Record whether packages were added or removed.
            $table->enum('direction', ['add', 'remove']);

            // Store how many whole packages were added or removed.
            $table->unsignedInteger('quantity');

            // Remember the available packages immediately after this movement.
            $table->unsignedInteger('resulting_balance');

            $table->timestamps();

            // Allow each stock item only once within the same entry.
            $table->unique(['stock_entry_id', 'stock_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
