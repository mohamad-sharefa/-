<?php

namespace App\Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Http\Requests\ListProductsRequest;
use App\Modules\Products\Http\Requests\StoreProductRequest;
use App\Modules\Products\Http\Resources\ProductResource;
use App\Modules\Products\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    private const DEFAULT_PER_PAGE = 12;

    /**
     * List products, newest first, optionally filtered by category.
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

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load('category'));
    }

    /**
     * Add a product with its image (admin only).
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        // store() gives the file a random name, so users cannot pick or overwrite paths
        $imagePath = $request->file('image')->store('products', 'public');

        $product = Product::create([
            ...$request->safe()->except('image'),
            'image_path' => $imagePath,
        ]);

        return (new ProductResource($product->load('category')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
