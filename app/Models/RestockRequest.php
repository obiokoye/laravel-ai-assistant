<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestockRequest extends Model
{
    protected $guarded = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->product->sku,
            'product' => $this->product->name,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'created' => $this->created_at?->gt(now()->subMinute()) ? 'just now' : $this->created_at?->diffForHumans(),
        ];
    }
}
