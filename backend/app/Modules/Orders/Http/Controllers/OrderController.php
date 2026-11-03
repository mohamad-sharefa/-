<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Http\Requests\StoreOrderRequest;
use App\Modules\Orders\Http\Requests\UpdateOrderStatusRequest;
use App\Modules\Orders\Http\Resources\OrderResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Customers get their own orders; admins get every order.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->visibleTo($request->user())
            ->with('user')
            ->withCount('items')
            ->withSum('items as quantity', 'quantity')
            ->latest()
            ->latest('id')
            ->paginate(10);

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->place($request->user(), $request->validated('items'));

        return (new OrderResource($order->load(['items', 'user'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Someone else's order is refused with 403 (OrderPolicy::view).
     */
    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load(['items', 'user']));
    }

    /**
     * Admin only (UpdateOrderStatusRequest::authorize).
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        $order = $this->orders->changeStatus($order, $request->status());

        return new OrderResource($order->load(['items', 'user']));
    }
}
