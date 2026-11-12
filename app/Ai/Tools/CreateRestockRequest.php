<?php

namespace App\Ai\Tools;

use App\Models\Product;
use Illuminate\Support\Facades\Validator;

/**
 * The assistant's only write action, and a deliberately limited one: it files
 * a *pending* request for a manager to approve. It never changes stock,
 * prices or orders itself.
 */
class CreateRestockRequest extends ShopTool
{
    public const MAX_QUANTITY = 200;

    public function __construct()
    {
        $this->as('create_restock_request')
            ->for('File a restock request for a product. A manager must approve it; this does not change stock. Use the SKU from another tool.')
            ->withStringParameter('sku', 'Product SKU, e.g. "KH-102"')
            ->withNumberParameter('quantity', 'Units to order, 1 to '.self::MAX_QUANTITY)
            ->withStringParameter('reason', 'Short reason, e.g. "Out of stock"', required: false)
            ->using($this);
    }

    public function __invoke(string $sku, int|float $quantity, ?string $reason = null): string
    {
        // Validate model-supplied input exactly like a Form Request would.
        $validator = Validator::make(
            ['sku' => strtoupper(trim($sku)), 'quantity' => $quantity, 'reason' => $reason],
            [
                'sku' => ['required', 'string', 'exists:products,sku'],
                'quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
                'reason' => ['nullable', 'string', 'max:200'],
            ],
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }

        $data = $validator->validated();
        $product = Product::firstWhere('sku', $data['sku']);

        // Don't stack duplicates: one pending request per product.
        $existing = $product->restockRequests()->where('status', 'pending')->first();
        if ($existing) {
            return $this->json([
                'created' => false,
                'message' => 'A pending request already exists for this product.',
                'request' => $existing->load('product')->toSummary(),
            ]);
        }

        $request = $product->restockRequests()->create([
            'quantity' => $data['quantity'],
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return $this->json([
            'created' => true,
            'request' => $request->load('product')->toSummary(),
            'next_step' => 'Waiting for a manager to approve.',
        ]);
    }
}
