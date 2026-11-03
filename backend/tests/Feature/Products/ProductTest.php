<?php

namespace Tests\Feature\Products;

use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_list_categories(): void
    {
        Category::factory()->count(3)->create();

        $this->getJson('/api/v1/categories')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_anyone_can_list_products_with_pagination(): void
    {
        Product::factory()->count(15)->create();

        $this->getJson('/api/v1/products?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonStructure(['data' => [['id', 'name', 'price', 'stock', 'image_url', 'category' => ['id', 'name']]], 'links', 'meta']);

        $this->getJson('/api/v1/products?per_page=10&page=2')->assertOk()->assertJsonCount(5, 'data');
    }

    public function test_products_can_be_filtered_by_category(): void
    {
        $books = Category::factory()->create();
        Product::factory()->count(2)->for($books)->create();
        Product::factory()->count(3)->create();

        $this->getJson("/api/v1/products?category_id={$books->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.category.id', $books->id);
    }

    public function test_listing_rejects_invalid_filters(): void
    {
        $this->getJson('/api/v1/products?category_id=999&per_page=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'per_page']);
    }

    public function test_anyone_can_view_a_product(): void
    {
        $product = Product::factory()->create(['price' => 45]);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.name', $product->name)
            ->assertJsonPath('data.price', '45.00');
    }

    public function test_viewing_a_missing_product_returns_404(): void
    {
        $this->getJson('/api/v1/products/999')->assertNotFound();
    }

    public function test_an_admin_can_add_a_product_with_an_image(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();
        $token = auth('api')->login(User::factory()->admin()->create());

        $response = $this->withToken($token)->post('/api/v1/products', [
            'name' => 'سماعات',
            'category_id' => $category->id,
            'price' => '45.50',
            'stock' => 10,
            'description' => 'وصف',
            'image' => UploadedFile::fake()->create('photo.jpg', 200, 'image/jpeg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'سماعات')
            ->assertJsonPath('data.price', '45.50')
            ->assertJsonPath('data.category.id', $category->id);

        $product = Product::first();
        Storage::disk('public')->assertExists($product->image_path);
        $this->assertStringContainsString('/storage/products/', $response->json('data.image_url'));
    }

    public function test_a_customer_cannot_add_products(): void
    {
        $token = auth('api')->login(User::factory()->create());

        $this->withToken($token)->postJson('/api/v1/products', [])->assertForbidden();
    }

    public function test_a_guest_cannot_add_products(): void
    {
        $this->postJson('/api/v1/products', [])->assertUnauthorized();
    }

    public function test_adding_a_product_validates_input_and_the_image_type(): void
    {
        Storage::fake('public');
        $token = auth('api')->login(User::factory()->admin()->create());

        $this->withToken($token)->post('/api/v1/products', [
            'price' => '-5',
            'stock' => 'many',
            'image' => UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'category_id', 'price', 'stock', 'image']);

        $this->assertDatabaseCount('products', 0);
    }
}
