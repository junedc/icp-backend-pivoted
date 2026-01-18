<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all()->keyBy('name');

        if ($categories->isEmpty()) {
            $this->command->warn('No categories found. Run CategorySeeder first.');
            return;
        }

        $products = [
            ['name' => 'iPhone 15', 'sku' => 'IPH-15-001', 'price' => 1499.00, 'stock_quantity' => 50, 'category' => 'Electronics'],
            ['name' => 'Bluetooth Headphones', 'sku' => 'ELEC-BT-002', 'price' => 199.99, 'stock_quantity' => 120, 'category' => 'Electronics'],
            ['name' => 'Air Fryer', 'sku' => 'HOME-AF-003', 'price' => 249.50, 'stock_quantity' => 30, 'category' => 'Home & Kitchen'],
            ['name' => 'Running Shoes', 'sku' => 'FASH-RUN-004', 'price' => 129.95, 'stock_quantity' => 80, 'category' => 'Fashion'],
            ['name' => 'Laravel for Professionals', 'sku' => 'BOOK-LAR-005', 'price' => 59.00, 'stock_quantity' => 200, 'category' => 'Books'],
            ['name' => 'Yoga Mat', 'sku' => 'SPORT-YOGA-006', 'price' => 39.99, 'stock_quantity' => 150, 'category' => 'Sports'],
        ];

        foreach ($products as $item) {
            $category = $categories->get($item['category']);
            if (!$category) {
                continue;
            }

            Product::updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'stock_quantity' => $item['stock_quantity'],
                    'category_id' => $category->id
                ]
            );
        }
    }
}
