<?php

namespace Tests\Feature;

use App\Ai\Tools\CreateRestockRequest;
use App\Ai\Tools\GetOrder;
use App\Ai\Tools\LowStockReport;
use App\Ai\Tools\SalesSummary;
use App\Ai\Tools\SearchProducts;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestockRequest;
use Tests\TestCase;

class ToolsTest extends TestCase
{
    public function test_search_matches_plural_words_and_reports_price_and_stock(): void
    {
        $result = $this->callTool(new SearchProducts, ['query' => 'candles']);

        $this->assertSame(2, $result['count']);
        $this->assertSame(['KH-108', 'KH-107'], array_column($result['products'], 'sku'));
        $this->assertSame(6500, $result['products'][0]['price']);
    }

    public function test_search_rejects_an_empty_query(): void
    {
        $this->assertArrayHasKey('error', $this->callTool(new SearchProducts, ['query' => '  ']));
    }

    public function test_low_stock_report_lists_lowest_stock_first(): void
    {
        $result = $this->callTool(new LowStockReport);

        $this->assertSame(5, $result['count']);
        $this->assertSame(2, $result['out_of_stock']);
        $this->assertSame(0, $result['products'][0]['stock']);
        $this->assertSame(12, $result['products'][0]['suggested_quantity']); // 6 * 2 - 0
    }

    public function test_low_stock_report_filters_by_category(): void
    {
        $result = $this->callTool(new LowStockReport, ['category' => 'lighting']);

        $this->assertSame(['KH-102', 'KH-101'], array_column($result['products'], 'sku'));
    }

    public function test_get_order_accepts_a_hash_prefix(): void
    {
        $result = $this->callTool(new GetOrder, ['order_number' => '#1042']);

        $this->assertSame('Chiamaka Eze', $result['customer']);
        $this->assertSame('shipped', $result['status']);
        $this->assertSame(63500, $result['total']);
        $this->assertCount(2, $result['items']);
    }

    public function test_get_order_reports_unknown_numbers(): void
    {
        $this->assertStringContainsString('No order', $this->callTool(new GetOrder, ['order_number' => '9999'])['error']);
    }

    public function test_sales_summary_excludes_cancelled_orders_and_clamps_days(): void
    {
        $all = $this->callTool(new SalesSummary, ['days' => 500]);

        $this->assertSame(90, $all['days']);
        $this->assertSame(Order::where('status', '!=', 'cancelled')->count(), $all['orders']);
        $this->assertSame((int) Order::where('status', '!=', 'cancelled')->sum('total'), $all['revenue']);
        $this->assertCount(3, $all['top_products']);
    }

    public function test_restock_request_is_created_as_pending(): void
    {
        $result = $this->callTool(new CreateRestockRequest, ['sku' => 'kh-102', 'quantity' => 12, 'reason' => 'Out of stock']);

        $this->assertTrue($result['created']);
        $this->assertSame('pending', $result['request']['status']);
        $this->assertDatabaseHas('restock_requests', ['quantity' => 12, 'status' => 'pending']);
        // The assistant can only *request* stock; the product itself is unchanged.
        $this->assertSame(0, Product::firstWhere('sku', 'KH-102')->stock);
    }

    public function test_restock_request_does_not_duplicate_a_pending_request(): void
    {
        $this->callTool(new CreateRestockRequest, ['sku' => 'KH-102', 'quantity' => 12]);
        $second = $this->callTool(new CreateRestockRequest, ['sku' => 'KH-102', 'quantity' => 30]);

        $this->assertFalse($second['created']);
        $this->assertSame(1, RestockRequest::count());
    }

    public function test_restock_request_validates_model_input(): void
    {
        $this->assertArrayHasKey('error', $this->callTool(new CreateRestockRequest, ['sku' => 'KH-102', 'quantity' => 5000]));
        $this->assertArrayHasKey('error', $this->callTool(new CreateRestockRequest, ['sku' => 'NOPE-1', 'quantity' => 5]));
        $this->assertSame(0, RestockRequest::count());
    }
}
