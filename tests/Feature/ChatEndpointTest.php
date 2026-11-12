<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\PrismManager;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Text\Request as TextRequest;
use Tests\TestCase;

class ChatEndpointTest extends TestCase
{
    public function test_page_shows_the_demo_notice_and_tools(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Demo mode: scripted AI replies')
            ->assertSee('create_restock_request')
            ->assertSee('Where is order #1042?');
    }

    public function test_message_is_validated_like_a_form_request(): void
    {
        $this->postJson('/chat', ['message' => '   '])->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->postJson('/chat', ['message' => str_repeat('a', 1001)])->assertJsonValidationErrors('message');
        $this->postJson('/chat', ['message' => 'hi', 'conversation_id' => 999])->assertJsonValidationErrors('conversation_id');
    }

    public function test_chat_returns_the_reply_tool_trace_and_restock_list(): void
    {
        $response = $this->postJson('/chat', ['message' => 'Which products are low? Please restock the empty ones.'])
            ->assertOk()
            ->assertJsonPath('reply.steps', 3)
            ->assertJsonPath('reply.tool_calls.0.name', 'low_stock_report')
            ->assertJsonCount(2, 'restock_requests');

        $id = $response->json('conversation_id');
        $this->postJson('/chat', ['message' => 'Where is order 1042?', 'conversation_id' => $id])
            ->assertOk()
            ->assertJsonPath('conversation_id', $id);

        $this->assertSame(4, ChatMessage::where('conversation_id', $id)->count());
    }

    public function test_live_mode_sends_tools_system_prompt_and_history_to_the_provider(): void
    {
        config(['assistant.demo' => false, 'assistant.prism_provider' => 'gemini', 'assistant.model' => 'gemini-2.5-flash']);
        $fake = Prism::fake([TextResponseFake::make()->withText('All good.')]);

        $this->postJson('/chat', ['message' => 'Hello'])->assertOk()->assertJsonPath('reply.content', 'All good.');

        $fake->assertRequest(function (array $requests) {
            /** @var TextRequest $request */
            $request = $requests[0];
            $this->assertSame('gemini-2.5-flash', $request->model());
            $this->assertSame(5, $request->maxSteps());
            $this->assertCount(5, $request->tools());
            $this->assertStringContainsString('Never guess', $request->systemPrompts()[0]->content);
            $this->assertSame('Hello', $request->messages()[0]->text());
        });
    }

    public function test_provider_errors_become_a_friendly_json_error(): void
    {
        config(['assistant.demo' => false, 'assistant.prism_provider' => 'gemini']);
        // A provider that always fails, like a bad API key would.
        app()->instance(PrismManager::class, new class(app()) extends PrismManager
        {
            public function resolve($name, array $providerConfig = []): Provider
            {
                throw new PrismException('401 Unauthorized');
            }
        });

        $this->postJson('/chat', ['message' => 'Hello'])
            ->assertStatus(502)
            ->assertJsonPath('error', 'The AI provider returned an error. Check your API key and model in .env.');
    }
}
