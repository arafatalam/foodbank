<?php

use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Support\Facades\Schema;

test('catalogue seeders build realistic three level data and can be rerun', function () {
    $seeders = [DepartmentSeeder::class, CategorySeeder::class, ProductSeeder::class];
    $this->seed($seeders);
    $counts = [Department::count(), Category::count(), Product::count()];
    expect($counts[0])->toBe(4)->and($counts[1])->toBeGreaterThan(20)->and($counts[2])->toBeGreaterThan(90);
    $rice = Product::query()->where('name', 'Rice')->sole();
    expect($rice->category->name)->toBe('Grains')->and($rice->category->department->name)->toBe('Food');
    $rice->update(['is_active' => false]);
    $this->seed($seeders);
    expect([Department::count(), Category::count(), Product::count()])->toBe($counts)->and($rice->fresh()->is_active)->toBeFalse();
    expect(Schema::hasTable('subcategories'))->toBeFalse()->and(Schema::hasTable('tags'))->toBeFalse()->and(Schema::hasTable('product_tag'))->toBeFalse();
    expect(Schema::getColumnListing('products'))->not->toContain('brand', 'subcategory_id', 'quantity');
});

test('the default seeder rebuilds accounts and the complete catalogue together', function () {
    $this->seed(DatabaseSeeder::class);
    expect(Department::count())->toBe(4)->and(Category::count())->toBe(25)->and(Product::count())->toBe(110);
    expect(User::query()->where('username', config('superadmin.username'))->exists())->toBeTrue();
    $this->assertDatabaseHas('products', ['name' => 'Rice', 'is_active' => true]);
});
