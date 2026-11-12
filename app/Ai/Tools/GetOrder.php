<?php

namespace App\Ai\Tools;

use App\Models\Order;
use App\Models\OrderItem;

class GetOrder extends ShopTool
{
    public function __construct()
    {
        $this->as('get_order')
            ->for('Look up one order by its number: status, customer, items, total and shipping details.')
            ->withStringParameter('order_number', 'The order number, e.g. "1042" or "#1042"')
            ->using($this);
    }

    public function __invoke(string $order_number): string
    {
        $number = preg_replace('/\D/', '', $order_number);
        $order = $number === '' ? null : Order::with('items.product')->firstWhere('number', $number);

        if (! $order) {
            return $this->error("No order found with number {$order_number}.");
        }

        return $this->json([
            'number' => $order->number,
            'status' => $order->status,
            'customer' => $order->customer_name,
            'city' => $order->city,
            'placed_at' => $order->placed_at->toDateTimeString(),
            'shipped_at' => $order->shipped_at?->toDateTimeString(),
            'courier' => $order->courier,
            'total' => $order->total,
            'items' => $order->items->map(fn (OrderItem $item) => [
                'product' => $item->product->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ])->all(),
        ]);
    }
}
