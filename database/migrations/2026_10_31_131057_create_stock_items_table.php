<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();

            // Connect to an existing Product and protect it from deletion.
            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            // Connect to a reusable Package size and protect it from deletion.
            $table->foreignId('package_size_id')
                ->constrained()
                ->restrictOnDelete();

            // Allow stock items without a brand.
            $table->string('brand')->nullable();

            // Store a cleaned brand value for consistent duplicate checks.
            $table->string('brand_key');

            // New choices start empty; only later stock movements may change this balance.
            $table->unsignedInteger('available_quantity')->default(0);

            // Record when this stock item was created and last updated.
            $table->timestamps();

            // Allow each Product, package and cleaned brand combination only once.
            $table->unique([
                'product_id',
                'package_size_id',
                'brand_key',
            ]);
        });
    }

    public function down(): void
    {
        // Remove this table when this migration is rolled back.
        Schema::dropIfExists('stock_items');
    }
};
