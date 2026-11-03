<?php

namespace Database\Factories\Products;

use App\Modules\Products\Inventory\InventoryService;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 1, 200),
        ];
    }

    /**
     * Give the product this quantity in the inventory service.
     */
    public function withStock(int $quantity): static
    {
        return $this->afterCreating(function (Product $product) use ($quantity) {
            app(InventoryService::class)->setStock($product->id, $quantity);
        });
    }
}
