<?php

namespace App\Ai\Tools;

use App\Models\Product;
use Illuminate\Support\Str;

class SearchProducts extends ShopTool
{
    public function __construct()
    {
        $this->as('search_products')
            ->for('Search the product catalogue by name, SKU or category. Returns price (naira) and stock for each match.')
            ->withStringParameter('query', 'Words to search for, e.g. "lamp", "KH-104" or "candles"')
            ->using($this);
    }

    public function __invoke(string $query): string
    {
        $term = trim($query);
        if ($term === '') {
            return $this->error('Give a word to search for.');
        }

        // Match each word anywhere in name, SKU or category (case-insensitive, parameter-bound).
        // "candles" should find "Soy Candle", so search the singular form too.
        $words = array_slice(preg_split('/\s+/', mb_strtolower($term)) ?: [], 0, 5);
        $words = array_unique([...$words, ...array_map(fn ($w) => Str::singular($w), $words)]);
        $products = Product::query()
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $like = '%'.$word.'%';
                    $q->orWhereRaw('lower(name) like ?', [$like])
                        ->orWhereRaw('lower(sku) like ?', [$like])
                        ->orWhereRaw('lower(category) like ?', [$like]);
                }
            })
            ->orderBy('name')
            ->limit(8)
            ->get();

        return $this->json([
            'query' => $term,
            'count' => $products->count(),
            'products' => $products->map(fn (Product $p) => [
                'sku' => $p->sku,
                'name' => $p->name,
                'category' => $p->category,
                'price' => $p->price,
                'stock' => $p->stock,
            ])->all(),
        ]);
    }
}
