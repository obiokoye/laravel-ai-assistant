<?php

namespace Tests;

use Database\Seeders\ShopSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Every test starts with the same seeded shop (in-memory SQLite). */
    protected $seed = true;

    protected $seeder = ShopSeeder::class;

    /** Call a tool the way Prism does and decode its JSON answer. */
    protected function callTool(object $tool, array $args = []): array
    {
        return json_decode($tool->handle(...$args), true);
    }
}
