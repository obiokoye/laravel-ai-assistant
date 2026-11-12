<?php

namespace App\Ai\Demo;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The scripted conversations demo mode can hold. Every number in a reply is
 * read from a real tool result, so the answers are true for the seeded shop.
 */
final class DemoScripts
{
    public const SAMPLE_QUESTIONS = [
        'Which products are running low? Raise restock requests for anything out of stock.',
        'Where is order #1042?',
        'How did sales do this week, and what was the best seller?',
        'How much are the candles, and how many do we have?',
    ];

    /** @return array<int, DemoScript> */
    public static function all(): array
    {
        return [
            self::lowStock(),
            self::order(),
            self::sales(),
            self::search(),
            self::fallback(),
        ];
    }

    public static function lowStock(): DemoScript
    {
        return new DemoScript('low-stock', '/\b(low|running out|out of stock|restock|reorder)\b/i', [
            fn () => [['low_stock_report', []]],

            function (Results $seen, string $userText) {
                $empty = array_filter($seen->last('low_stock_report')['products'] ?? [], fn ($p) => $p['stock'] === 0);
                if ($empty === [] || ! preg_match('/\b(restock|request|reorder|order more)\b/i', $userText)) {
                    return self::lowStockAnswer($seen);
                }

                return array_values(array_map(fn ($p) => ['create_restock_request', [
                    'sku' => $p['sku'],
                    'quantity' => $p['suggested_quantity'],
                    'reason' => 'Out of stock',
                ]], $empty));
            },

            fn (Results $seen) => self::lowStockAnswer($seen),
        ]);
    }

    private static function lowStockAnswer(Results $seen): string
    {
        $report = $seen->last('low_stock_report');
        $lines = array_map(
            fn ($p) => "- **{$p['name']}** ({$p['sku']}): {$p['stock']} left, reorder level {$p['reorder_level']}",
            $report['products'] ?? [],
        );

        $text = "**{$report['count']} products** are at or below their reorder level:\n".implode("\n", $lines);

        $requests = $seen->all('create_restock_request');
        if ($requests !== []) {
            $done = array_map(function ($r) {
                $req = $r['request'] ?? [];
                $note = ($r['created'] ?? false) ? '' : ' (already pending)';

                return "{$req['quantity']} × {$req['product']}{$note}";
            }, array_filter($requests, fn ($r) => isset($r['request'])));

            $text .= "\n\nI've filed restock requests for the out-of-stock items: ".implode(' and ', $done)
                .'. They are waiting for a manager to approve them.';
        }

        return $text;
    }

    public static function order(): DemoScript
    {
        return new DemoScript('order', '/(order\s*(number|no\.?)?\s*#?\s*\d{3,6}|#\d{3,6})/i', [
            function (Results $seen, string $userText) {
                preg_match('/#?\s*(\d{3,6})/', $userText, $m);

                return [['get_order', ['order_number' => $m[1] ?? '']]];
            },

            function (Results $seen) {
                $o = $seen->last('get_order');
                if (isset($o['error'])) {
                    return "I couldn't find that order. Could you double-check the number?";
                }

                $items = implode("\n", array_map(
                    fn ($i) => "- {$i['quantity']} × {$i['product']} at ".self::naira($i['unit_price']),
                    $o['items'],
                ));
                $placed = Carbon::parse($o['placed_at'])->format('j M, g:i a');
                $shipping = $o['shipped_at']
                    ? ' It shipped with '.$o['courier'].' on '.Carbon::parse($o['shipped_at'])->format('j M, g:i a').'.'
                    : '';

                return "Order **#{$o['number']}** for {$o['customer']} ({$o['city']}) is **{$o['status']}**. "
                    ."It was placed on {$placed}.{$shipping}\n\n{$items}\n\nTotal: **".self::naira($o['total']).'**';
            },
        ]);
    }

    public static function sales(): DemoScript
    {
        return new DemoScript('sales', '/\b(sales|revenue|best.?sell\w*|top products?)\b/i', [
            function (Results $seen, string $userText) {
                $days = match (true) {
                    (bool) preg_match('/(\d{1,2})\s*days?/i', $userText, $m) => (int) $m[1],
                    (bool) preg_match('/month/i', $userText) => 30,
                    (bool) preg_match('/today/i', $userText) => 1,
                    default => 7,
                };

                return [['sales_summary', ['days' => $days]]];
            },

            function (Results $seen) {
                $s = $seen->last('sales_summary');
                $top = $s['top_products'] ?? [];
                $best = $top[0] ?? null;
                $rest = implode("\n", array_map(
                    fn ($p) => "- {$p['product']}: {$p['units']} units, ".self::naira($p['revenue']),
                    $top,
                ));

                $text = "In the last {$s['days']} days you had **{$s['orders']} orders** worth **"
                    .self::naira($s['revenue']).'** (average '.self::naira($s['average_order']).' per order).';

                if ($best) {
                    $text .= "\n\nBest seller: **{$best['product']}** with {$best['units']} units sold.\n\nTop products:\n{$rest}";
                }

                return $text;
            },
        ]);
    }

    public static function search(): DemoScript
    {
        return new DemoScript('search', '/\b(price|prices|cost|how much|how many|in stock|do we have|search|find)\b/i', [
            fn (Results $seen, string $userText) => [['search_products', ['query' => self::keywords($userText)]]],

            function (Results $seen) {
                $r = $seen->last('search_products');
                if (($r['count'] ?? 0) === 0) {
                    return "I couldn't find any products matching \"{$r['query']}\".";
                }

                $lines = array_map(
                    fn ($p) => "- **{$p['name']}** ({$p['sku']}): ".self::naira($p['price']).", {$p['stock']} in stock",
                    $r['products'],
                );

                return "Here's what I found for \"{$r['query']}\":\n".implode("\n", $lines);
            },
        ]);
    }

    public static function fallback(): DemoScript
    {
        return new DemoScript('fallback', '/.*/', [
            fn () => "I'm running in **demo mode** with scripted replies, so I can only handle a few kinds of question. Try one of these:\n"
                .implode("\n", array_map(fn ($q) => "- {$q}", self::SAMPLE_QUESTIONS))
                ."\n\nAdd a free Gemini key to `.env` to ask anything.",
        ]);
    }

    private static function keywords(string $text): string
    {
        $stop = ['how', 'much', 'many', 'are', 'is', 'the', 'and', 'do', 'we', 'have', 'our', 'what', 'price', 'prices',
            'cost', 'of', 'in', 'stock', 'search', 'find', 'for', 'a', 'an', 'any', 'left', 'does', 'it', 'they', 'me', 'show'];
        $words = preg_split('/[^a-z0-9-]+/', Str::lower($text)) ?: [];
        $kept = array_filter($words, fn ($w) => $w !== '' && ! in_array($w, $stop, true));

        return implode(' ', array_slice(array_values($kept), 0, 3)) ?: 'lamp';
    }

    private static function naira(int|float $amount): string
    {
        return '₦'.number_format((float) $amount);
    }
}
