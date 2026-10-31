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
        Schema::create('package_sizes', function (Blueprint $table) {
            $table->id();
            // Store the kind of container, such as bag, box or bottle.
            $table->string('package_type');
            // Store the amount inside one package, allowing three decimal places.
            $table->decimal('contents', 12, 3);
            // Store the measurement unit, such as kg, g, ml, litre or item.
            $table->string('contents_unit');
            // Prevent two equivalent package definitions from being saved.
            $table->string('definition_key', 100)->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_sizes');
    }
};
