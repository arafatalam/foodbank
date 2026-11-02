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
        Schema::create('stock_entries', function (Blueprint $table) {

            $table->id();

            // Identify which kind of stock operation this entry records.
            $table->enum('type', ['receive', 'distribute', 'adjust']);

            // Record the user responsible and preserve that connection.
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            // Allow one optional note for the whole receipt.
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_entries');
    }
};
