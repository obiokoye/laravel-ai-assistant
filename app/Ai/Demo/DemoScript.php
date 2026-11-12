<?php

namespace App\Ai\Demo;

use Closure;

/**
 * One scripted conversation. Each turn plays the model for one step: it
 * receives the tool results so far and returns either tool calls
 * (a list of [tool_name, args]) or the final answer (a string).
 */
final class DemoScript
{
    /** @param  array<int, Closure(Results, string): (string|array<int, array{0: string, 1: array<string, mixed>}>)>  $turns */
    public function __construct(
        public readonly string $name,
        public readonly string $pattern,
        public readonly array $turns,
    ) {}

    public function matches(string $userText): bool
    {
        return (bool) preg_match($this->pattern, $userText);
    }
}
