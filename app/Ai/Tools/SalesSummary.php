<?php

namespace App\Ai\Tools;

use App\Models\Order;
use App\Models\OrderItem;

class SalesSummary extends ShopTool
{
    public function __construct()
    {
        $this->as('sales_summary')
            ->for('Sales for the last N days (cancelled orders excluded): order count, revenue in naira, average order value and the top 3 products by units sold.')
            ->withNumberParameter('days', 'How many days to look back, 1 to 90. Use 7 for "this week".')
            ->using($this);
    }

    public function __invoke(int|float $days = 7): string
    {
        $days = max(1, min(90, (int) $days));

        $orders = Order::query()
            ->where('status', '!=', 'cancelled')
            ->where('placed_at', '>=', now()->subDays($days))
            ->pluck('total', 'id');

        $top = OrderItem::query()
            ->whereIn('order_id', $orders->keys())
            ->selectRaw('product_id, sum(quantity) as units, sum(quantity * unit_price) as revenue')
            ->groupBy('product_id')
            ->orderByDesc('units')
            ->orderByDesc('revenue')
            ->with('product')
            ->limit(3)
            ->get();

        $count = $orders->count();
        $revenue = (int) $orders->sum();

        return $this->json([
            'days' => $days,
            'orders' => $count,
            'revenue' => $revenue,
            'average_order' => $count ? (int) round($revenue / $count) : 0,
            'top_products' => $top->map(fn ($row) => [
                'product' => $row->product->name,
                'units' => (int) $row->units,
                'revenue' => (int) $row->revenue,
            ])->all(),
        ]);
    }
}
