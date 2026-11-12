<?php

namespace App\Http\Controllers;

use App\Ai\Demo\DemoScripts;
use App\Ai\ShopAssistant;
use App\Http\Requests\ChatRequest;
use App\Models\Conversation;
use App\Models\RestockRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Tool;
use Throwable;

class ChatController extends Controller
{
    public function __construct(private readonly ShopAssistant $assistant) {}

    public function index(): View
    {
        if ($this->assistant->isDemo()) {
            Log::info('Demo mode: scripted AI replies');
        }

        return view('chat', [
            'demo' => $this->assistant->isDemo(),
            'provider' => config('assistant.provider'),
            'model' => $this->assistant->model(),
            'store' => config('assistant.store_name'),
            'samples' => DemoScripts::SAMPLE_QUESTIONS,
            'tools' => array_map(fn (Tool $t) => ['name' => $t->name(), 'description' => $t->description()], $this->assistant->tools()),
            'restockRequests' => $this->restockRequests(),
        ]);
    }

    public function store(ChatRequest $request): JsonResponse
    {
        $conversation = $request->filled('conversation_id')
            ? Conversation::findOrFail($request->integer('conversation_id'))
            : Conversation::create();

        try {
            $reply = $this->assistant->reply($conversation, $request->string('message')->toString());
        } catch (PrismRateLimitedException) {
            return response()->json(['error' => 'The AI provider is rate limiting requests. Wait a minute and try again.'], 429);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'The AI provider returned an error. Check your API key and model in .env.'], 502);
        }

        return response()->json([
            'conversation_id' => $conversation->id,
            'reply' => [
                'content' => $reply->content,
                'tool_calls' => $reply->tool_calls ?? [],
                'steps' => $reply->steps,
                'tokens' => ($reply->prompt_tokens ?? 0) + ($reply->completion_tokens ?? 0),
            ],
            'restock_requests' => $this->restockRequests(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function restockRequests(): array
    {
        return RestockRequest::with('product')->latest('id')->take(6)->get()
            ->map(fn (RestockRequest $r) => $r->toSummary())->all();
    }
}
