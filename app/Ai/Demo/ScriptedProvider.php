<?php

namespace App\Ai\Demo;

use Prism\Prism\Concerns\CallsTools;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Text\Request;
use Prism\Prism\Text\Response;
use Prism\Prism\Text\ResponseBuilder;
use Prism\Prism\Text\Step;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\ToolResult;
use Prism\Prism\ValueObjects\Usage;

/**
 * A Prism provider that plays the model from scripts instead of calling an
 * API. It runs the same loop as Prism's real providers: ask the "model",
 * execute the tools it requests (the real tools, against the real database),
 * feed the results back, and repeat until it answers or hits maxSteps.
 *
 * Used by the tests and by demo mode, so the app works with no API key.
 */
class ScriptedProvider extends Provider
{
    use CallsTools;

    /** @param  array<int, DemoScript>  $scripts  The last script is the fallback. */
    public function __construct(
        private readonly array $scripts,
        private readonly int $delayMs = 0,
    ) {}

    public function text(Request $request): Response
    {
        $builder = new ResponseBuilder;
        $userText = $this->lastUserText($request);
        $script = $this->pick($userText);
        $seen = new Results;

        foreach ($script->turns as $turnIndex => $turn) {
            if ($builder->steps->count() >= $request->maxSteps()) {
                break;
            }

            $this->pause();
            $output = $turn($seen, $userText);

            if (is_string($output)) {
                $builder->addStep($this->step($request, $output, FinishReason::Stop));

                return $builder->toResponse();
            }

            $toolCalls = array_map(
                fn (array $call, int $i) => new ToolCall("call_{$turnIndex}_{$i}", $call[0], $call[1]),
                $output,
                array_keys($output),
            );

            $toolResults = $this->callTools($request->tools(), $toolCalls);
            $builder->addStep($this->step($request, '', FinishReason::ToolCalls, $toolCalls, $toolResults));

            $request->addMessage(new AssistantMessage('', $toolCalls));
            $request->addMessage(new ToolResultMessage($toolResults));
            $seen = $seen->with($toolResults);
        }

        return $builder->toResponse();
    }

    private function pick(string $userText): DemoScript
    {
        foreach ($this->scripts as $script) {
            if ($script->matches($userText)) {
                return $script;
            }
        }

        return $this->scripts[array_key_last($this->scripts)];
    }

    private function lastUserText(Request $request): string
    {
        foreach (array_reverse($request->messages()) as $message) {
            if ($message instanceof UserMessage) {
                return $message->text();
            }
        }

        return (string) $request->prompt();
    }

    /**
     * @param  array<int, ToolCall>  $toolCalls
     * @param  array<int, ToolResult>  $toolResults
     */
    private function step(Request $request, string $text, FinishReason $reason, array $toolCalls = [], array $toolResults = []): Step
    {
        return new Step(
            text: $text,
            finishReason: $reason,
            toolCalls: $toolCalls,
            toolResults: $toolResults,
            providerToolCalls: [],
            usage: new Usage(0, 0),
            meta: new Meta('demo', 'scripted-demo'),
            messages: $request->messages(),
            systemPrompts: $request->systemPrompts(),
        );
    }

    private function pause(): void
    {
        if ($this->delayMs > 0) {
            usleep($this->delayMs * 1000);
        }
    }
}
