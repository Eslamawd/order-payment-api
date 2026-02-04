<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItems;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderCollection;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{


    public function index(Request $request)
    {
        $query = Order::with(['items', 'payments'])
            ->where('user_id', auth()->id())
            ->latest();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate($request->get('per_page', 15));

        return new OrderCollection($orders);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_address' => 'required|string',
            'customer_phone' => 'required|string|max:20',
            'items' => 'required|array|min:1',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'tax' => 'numeric|min:0',
            'shipping' => 'numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            // Calculate totals
            $subtotal = collect($request->items)->sum(function ($item) {
                return $item['quantity'] * $item['price'];
            });

            $tax = $request->tax ?? 0;
            $shipping = $request->shipping ?? 0;
            $total = $subtotal + $tax + $shipping;

            // Create order
            $order = Order::create([
                'user_id' => auth()->id(),
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_address' => $request->customer_address,
                'customer_phone' => $request->customer_phone,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping' => $shipping,
                'total' => $total,
                'notes' => $request->notes,
            ]);

            // Create order items
            foreach ($request->items as $item) {
                OrderItems::create([
                    'order_id' => $order->id,
                    'product_name' => $item['product_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['quantity'] * $item['price'],
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Order created successfully',
                'data' => new OrderResource($order->load('items'))
            ], 201);
        });
    }

    public function show($id)
    {
        $order = Order::with(['items', 'payments'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => new OrderResource($order)
        ]);
    }

    public function update(Request $request, $id)
    {
        $order = Order::where('user_id', auth()->id())
            ->findOrFail($id);

        // Check if order has payments
        if ($order->payments()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot update order with existing payments'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'customer_name' => 'sometimes|required|string|max:255',
            'customer_email' => 'sometimes|required|email|max:255',
            'customer_address' => 'sometimes|required|string',
            'customer_phone' => 'sometimes|required|string|max:20',
            'status' => 'sometimes|required|in:pending,confirmed,cancelled',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $order->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Order updated successfully',
            'data' => new OrderResource($order->fresh()->load('items'))
        ]);
    }

    public function destroy($id)
    {
        $order = Order::where('user_id', auth()->id())
            ->findOrFail($id);

        // Check if order has payments
        if (!$order->canBeDeleted()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete order with associated payments'
            ], 400);
        }

        $order->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Order deleted successfully'
        ]);
    }

    public function confirm($id)
    {
        $order = Order::where('user_id', auth()->id())
            ->findOrFail($id);

        if ($order->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Order is not in pending status'
            ], 400);
        }

        $order->update(['status' => 'confirmed']);

        return response()->json([
            'status' => 'success',
            'message' => 'Order confirmed successfully',
            'data' => new OrderResource($order)
        ]);
    }

    public function cancel($id)
    {
        $order = Order::where('user_id', auth()->id())
            ->findOrFail($id);

        if ($order->status === 'cancelled') {
            return response()->json([
                'status' => 'error',
                'message' => 'Order is already cancelled'
            ], 400);
        }

        $order->update(['status' => 'cancelled']);

        return response()->json([
            'status' => 'success',
            'message' => 'Order cancelled successfully',
            'data' => new OrderResource($order)
        ]);
    }
}