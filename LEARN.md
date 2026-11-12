# LEARN: Day 3, an AI agent inside a Laravel app

You already know Laravel, so this guide skips the framework and focuses on the AI agent ideas: what Prism does, how tool calling works, and how the app stays safe and testable.

## 1. Where Prism fits

Prism is to LLMs what the `Http` client and Mail drivers are to their services: one fluent API, many providers behind it.

```php
$response = Prism::text()
    ->using('gemini', 'gemini-2.5-flash')   // provider + model, from config/assistant.php
    ->withSystemPrompt($this->systemPrompt())
    ->withMessages($history)                // earlier turns from the database
    ->withTools($this->tools())             // what the model is allowed to do
    ->withMaxSteps(5)                       // the agent loop's safety cap
    ->asText();
```

That call is in `app/Ai/ShopAssistant.php`. Swapping Gemini for Groq or Anthropic changes only the `using()` arguments, which come from `.env`, the same way `MAIL_MAILER` switches mail drivers.

## 2. Tool calling: the key concept

A model can't query your database. **Tool calling** is a contract:

1. We send the model the conversation **plus a description of each tool**: name, purpose and parameters (Prism turns our tool classes into JSON Schema).
2. The model replies with text *or* a request like `get_order({"order_number": "1042"})`.
3. **We** run the PHP for that tool and send the result back.
4. The model reads the result and answers, or asks for another tool.

Steps 2 to 4 repeat. That repetition is the **agent loop**, and Prism runs it for you: when `withMaxSteps(5)` is set, it keeps going until the model gives a final answer or five model calls have happened. The step cap works like `$tries` on a queued job: it stops a confused model from looping forever (and spending your quota).

The screenshot in the README shows three steps for one question:

| Step | The model asked for | Our code did |
|------|---------------------|--------------|
| 1 | `low_stock_report()` | Eloquent query with the `lowStock` scope |
| 2 | `create_restock_request(...)` twice, in one turn | validated and inserted two pending rows |
| 3 | nothing, it answered | the answer was saved with a trace of steps 1 and 2 |

## 3. A tool is just a class

```php
class GetOrder extends ShopTool
{
    public function __construct()
    {
        $this->as('get_order')
            ->for('Look up one order by its number: status, customer, items, total and shipping details.')
            ->withStringParameter('order_number', 'The order number, e.g. "1042" or "#1042"')
            ->using($this);
    }

    public function __invoke(string $order_number): string { /* Eloquent, then JSON */ }
}
```

Think of it as an invokable controller whose "route" is chosen by the model. The **description matters**: it's the only documentation the model reads, so write it for the model, like a good docblock.

Tools return `{"error": "..."}` instead of throwing. The model reads the error and can recover (ask the user, try another number) rather than the whole request failing.

## 4. Keeping it safe

- **Treat model output like user input.** `CreateRestockRequest` runs the model's arguments through `Validator::make()` with real rules (`exists:products,sku`, `max:200`), exactly as a Form Request would.
- **Least privilege.** The assistant has one write tool, and it can only create a *pending* request for a human to approve. It cannot change stock, prices or orders, so a bad answer or a prompt injection can't do much damage.
- **Prompt injection.** Tool results could contain text written by customers (a product review, an order note). The system prompt says to treat tool output as data, not instructions. That reduces the risk but doesn't remove it, which is why least privilege matters more.
- **Plain old web security still applies.** `POST /chat` uses CSRF, a Form Request, and `throttle:20,1` because each message can trigger several paid model calls. The browser escapes all model text before adding its own formatting.
- **Secrets** stay in `.env`, which is git-ignored.

## 5. Testing an agent without an API key

Two techniques, both in `tests/Feature`:

- **`Prism::fake()`** (like `Http::fake()`): returns a canned response and records the request, so `ChatEndpointTest` can assert we sent the right model, five tools, the system prompt and the history.
- **`ScriptedProvider`**: a custom Prism provider, registered with `PrismManager::extend('scripted', ...)` the way you'd register a custom filesystem or mail driver. Unlike the fake, it runs the full loop and the **real tools** against an in-memory SQLite database. `AgentLoopTest` uses it to check multi-step runs, error recovery and the step cap.

Demo mode uses the same `ScriptedProvider`, so what you see in the demo is what the tests check.

## Try this

1. Add a `customer_orders` tool that lists a customer's recent orders by name, then ask "What has Tolu Adebayo ordered?" with a real Gemini key.
2. Set `ASSISTANT_MAX_STEPS=1` in `.env` and ask the low-stock question again. What happens to the restock step, and why?
3. Add an `approve` button next to each restock request in the sidebar (a normal controller action, no AI). The model files requests and a human approves them. This is the "human in the loop" pattern you'll see again later in the series.
