<?php

namespace Tests\Feature;

use App\Ai\Demo\DemoScript;
use App\Ai\Demo\Results;
use App\Ai\Demo\ScriptedProvider;
use App\Ai\ShopAssistant;
use App\Models\Conversation;
use App\Models\RestockRequest;
use Prism\Prism\PrismManager;
use Tests\TestCase;

class AgentLoopTest extends TestCase
{
    private function reply(string $text, ?Conversation $conversation = null)
    {
        return app(ShopAssistant::class)->reply($conversation ?? Conversation::create(), $text);
    }

    /** Swap in our own scripted "model" for one test. */
    private function script(DemoScript $script): void
    {
        app(PrismManager::class)->extend('scripted', fn () => new ScriptedProvider([$script]));
    }

    public function test_multi_step_run_reads_then_writes_then_answers(): void
    {
        $reply = $this->reply('Which products are running low? Raise restock requests for anything out of stock.');

        $this->assertSame(3, $reply->steps);
        $this->assertSame(
            ['low_stock_report', 'create_restock_request', 'create_restock_request'],
            array_column($reply->tool_calls, 'name'),
        );
        $this->assertSame([1, 2, 2], array_column($reply->tool_calls, 'step'));
        $this->assertSame(2, RestockRequest::where('status', 'pending')->count());
        $this->assertStringContainsString('12 × Brass Table Lamp', $reply->content);
    }

    public function test_answers_use_real_tool_results(): void
    {
        $reply = $this->reply('Where is order #1042?');

        $this->assertSame('get_order', $reply->tool_calls[0]['name']);
        $this->assertSame(['order_number' => '1042'], $reply->tool_calls[0]['args']);
        $this->assertStringContainsString('₦63,500', $reply->content);
    }

    public function test_conversation_history_is_stored_in_order(): void
    {
        $conversation = Conversation::create();
        $this->reply('Where is order #1042?', $conversation);
        $this->reply('How much are the candles?', $conversation);

        $this->assertSame(['user', 'assistant', 'user', 'assistant'], $conversation->messages()->orderBy('id')->pluck('role')->all());
        $this->assertSame('Where is order #1042?', $conversation->fresh()->title);
    }

    public function test_tool_errors_are_fed_back_to_the_model(): void
    {
        $this->script(new DemoScript('bad-args', '/.*/', [
            fn () => [['create_restock_request', ['sku' => 'KH-102', 'quantity' => 9999]]],
            fn (Results $seen) => 'Tool said: '.$seen->last('create_restock_request')['error'],
        ]));

        $reply = $this->reply('Order loads of lamps');

        $this->assertStringContainsString('must not be greater than 200', $reply->content);
        $this->assertSame(0, RestockRequest::count());
    }

    public function test_max_steps_stops_a_runaway_loop(): void
    {
        config(['assistant.max_steps' => 2]);
        $this->script(new DemoScript('loop', '/.*/', array_fill(0, 10, fn () => [['low_stock_report', []]])));

        $reply = $this->reply('anything');

        $this->assertSame(2, $reply->steps);
        $this->assertStringContainsString('ran out of steps', $reply->content);
    }

    public function test_unknown_questions_get_the_demo_help_text_without_tools(): void
    {
        $reply = $this->reply('Write me a poem');

        $this->assertSame([], $reply->tool_calls);
        $this->assertStringContainsString('demo mode', $reply->content);
    }
}
