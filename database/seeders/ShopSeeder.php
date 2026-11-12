<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * A small, deterministic shop: same products and orders every time, with
 * dates relative to "now" so the sales numbers always look current.
 */
class ShopSeeder extends Seeder
{
    /** @var array<int, array{0: string, 1: string, 2: string, 3: int, 4: int, 5: int}> sku, name, category, price, stock, reorder level */
    public const PRODUCTS = [
        ['KH-101', 'Rattan Pendant Lamp', 'Lighting', 38500, 4, 8],
        ['KH-102', 'Brass Table Lamp', 'Lighting', 27000, 0, 6],
        ['KH-103', 'Adire Throw Pillow', 'Textiles', 12500, 46, 15],
        ['KH-104', 'Aso-oke Table Runner', 'Textiles', 15000, 3, 10],
        ['KH-105', 'Handwoven Raffia Basket', 'Storage', 9800, 22, 12],
        ['KH-106', 'Terracotta Vase (Large)', 'Decor', 18000, 0, 5],
        ['KH-107', 'Soy Candle, Shea & Vanilla', 'Candles', 6500, 80, 25],
        ['KH-108', 'Soy Candle, Lemongrass', 'Candles', 6500, 12, 25],
        ['KH-109', 'Stoneware Mug Set (4)', 'Kitchen', 14000, 31, 10],
        ['KH-110', 'Mahogany Serving Board', 'Kitchen', 21000, 17, 6],
        ['KH-111', 'Woven Floor Rug 120x180', 'Textiles', 64000, 7, 4],
        ['KH-112', 'Ceramic Planter', 'Decor', 11500, 26, 8],
    ];

    private const CUSTOMERS = [
        ['Tolu Adebayo', 'Lagos'], ['Ngozi Okafor', 'Enugu'], ['Ibrahim Musa', 'Kano'],
        ['Funmi Ajayi', 'Ibadan'], ['Emeka Nwosu', 'Port Harcourt'], ['Aisha Bello', 'Abuja'],
        ['Kunle Ogun', 'Lagos'], ['Blessing Uche', 'Owerri'], ['Segun Bakare', 'Abeokuta'],
        ['Halima Sani', 'Kaduna'], ['Ifeoma Obi', 'Lagos'], ['David Etim', 'Calabar'],
    ];

    private const COURIERS = ['GIG Logistics', 'DHL', 'Kwik Delivery'];

    public function run(): void
    {
        $products = collect(self::PRODUCTS)->map(fn (array $p) => Product::create([
            'sku' => $p[0], 'name' => $p[1], 'category' => $p[2],
            'price' => $p[3], 'stock' => $p[4], 'reorder_level' => $p[5],
        ]))->values();

        for ($i = 0; $i < 28; $i++) {
            $number = (string) (1020 + $i);
            $placedAt = now()->subHours((27 - $i) * 11 + 3)->startOfMinute();
            [$name, $city] = self::CUSTOMERS[$i % count(self::CUSTOMERS)];

            $lines = [[$products[($i * 5) % 12], ($i % 3) + 1]];
            if ($i % 2 === 0) {
                $lines[] = [$products[($i * 7 + 3) % 12], 1];
            }

            if ($number === '1042') {
                [$name, $city] = ['Chiamaka Eze', 'Abuja'];
                $lines = [[$products[0], 1], [$products[2], 2]];
            }

            $ageInHours = (27 - $i) * 11 + 3;
            $status = match (true) {
                $number === '1035' => 'cancelled',
                $ageInHours > 120 => 'delivered',
                $ageInHours > 40 => 'shipped',
                $i === 27 => 'pending',
                default => 'paid',
            };
            $shipped = in_array($status, ['shipped', 'delivered'], true);

            $order = Order::create([
                'number' => $number,
                'customer_name' => $name,
                'city' => $city,
                'status' => $status,
                'total' => collect($lines)->sum(fn (array $l) => $l[0]->price * $l[1]),
                'placed_at' => $placedAt,
                'shipped_at' => $shipped ? $placedAt->copy()->addHours(20) : null,
                'courier' => $shipped ? self::COURIERS[$i % 3] : null,
            ]);

            foreach ($lines as [$product, $qty]) {
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $product->price,
                ]);
            }
        }
    }
}
