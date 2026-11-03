<?php

namespace App\Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Http\Requests\ListProductsRequest;
use App\Modules\Products\Http\Requests\StoreProductRequest;
use App\Modules\Products\Http\Resources\ProductResource;
use App\Modules\Products\Inventory\InventoryService;
use App\Modules\Products\Inventory\InventoryUnavailableException;
use App\Modules\Products\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductController extends Controller
{
    private const DEFAULT_PER_PAGE = 12;

    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * List products, newest first, optionally filtered by category.
     * Quantities come from the inventory service in one gRPC call for the whole page.
     */
    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with('category')
            ->when($request->validated('category_id'), fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->latest()
            ->latest('id')
            ->paginate($request->validated('per_page', self::DEFAULT_PER_PAGE))
            ->withQueryString();

        $this->attachStock($products->getCollection());

        return ProductResource::collection($products);
    }

    /**
     * Product details with the quantity the inventory service reports.
     */
    public function show(Product $product): ProductResource
    {
        $this->attachStock(collect([$product]));

        return new ProductResource($product->load('category'));
    }

    /**
     * Add a product with its image (admin only); its stock is set in the inventory service.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        // store() gives the file a random name, so users cannot pick or overwrite paths
        $imagePath = $request->file('image')->store('products', 'public');

        try {
            // If the inventory service can't take the stock, the product isn't created either
            $product = DB::transaction(function () use ($request, $imagePath) {
                $product = Product::create([
                    ...$request->safe()->except(['image', 'stock']),
                    'image_path' => $imagePath,
                ]);
                $this->inventory->setStock($product->id, (int) $request->validated('stock'));

                return $product;
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($imagePath);
            throw $e;
        }

        $product->stock = (int) $request->validated('stock');

        return (new ProductResource($product->load('category')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Fill in each product's quantity. If the inventory service is down or slow the products are
     * still shown, with an unknown quantity (null), instead of failing the whole page.
     *
     * @param  Collection<int, Product>  $products
     */
    private function attachStock(Collection $products): void
    {
        try {
            $stock = $this->inventory->stockOf($products->pluck('id')->all());
        } catch (InventoryUnavailableException) {
            return;
        }

        $products->each(fn (Product $product) => $product->stock = $stock[$product->id] ?? 0);
    }
}
