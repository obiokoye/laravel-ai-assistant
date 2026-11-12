<?php

namespace App\Ai\Tools;

use App\Models\Product;

class LowStockReport extends ShopTool
{
    public function __construct()
    {
        $this->as('low_stock_report')
            ->for('List products at or below their reorder level, lowest stock first, with a suggested reorder quantity.')
            ->withStringParameter('category', 'Optional category filter, e.g. "Lighting". Leave empty for all.', required: false)
            ->using($this);
    }

    public function __invoke(?string $category = null): string
    {
        $products = Product::query()
            ->lowStock()
            ->when(filled($category), fn ($q) => $q->whereRaw('lower(category) = ?', [mb_strtolower(trim((string) $category))]))
            ->orderBy('stock')
            ->orderBy('name')
            ->get();

        return $this->json([
            'count' => $products->count(),
            'out_of_stock' => $products->where('stock', 0)->count(),
            'products' => $products->map(fn (Product $p) => [
                'sku' => $p->sku,
                'name' => $p->name,
                'stock' => $p->stock,
                'reorder_level' => $p->reorder_level,
                // Enough to get back to twice the reorder level.
                'suggested_quantity' => max(1, $p->reorder_level * 2 - $p->stock),
            ])->values()->all(),
        ]);
    }
}
