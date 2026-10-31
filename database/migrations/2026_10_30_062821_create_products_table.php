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
        Schema::create('products', function (Blueprint $table) {

            $table->id();
            $table->string('name');
            $table->foreignId('category_id')
                ->constrained()
                ->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            // Enforce trimmed, case-insensitive names within each category.
            $table->string('name_key')->storedAs('lower(trim(name))');
            $table->unique(['category_id', 'name_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
