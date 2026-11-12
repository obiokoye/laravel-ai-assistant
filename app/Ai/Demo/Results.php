<?php

namespace App\Ai\Demo;

use Prism\Prism\ValueObjects\ToolResult;

/** The tool results a scripted model has "seen" so far, decoded from JSON. */
final class Results
{
    /** @param  array<int, ToolResult>  $results */
    public function __construct(private array $results = []) {}

    /** @param  array<int, ToolResult>  $more */
    public function with(array $more): self
    {
        return new self([...$this->results, ...$more]);
    }

    /** Last result from the named tool. @return array<string, mixed> */
    public function last(string $tool): array
    {
        foreach (array_reverse($this->results) as $result) {
            if ($result->toolName === $tool) {
                return self::decode($result->result);
            }
        }

        return [];
    }

    /** Every result from the named tool, in order. @return array<int, array<string, mixed>> */
    public function all(string $tool): array
    {
        return array_values(array_map(
            fn (ToolResult $r) => self::decode($r->result),
            array_filter($this->results, fn (ToolResult $r) => $r->toolName === $tool),
        ));
    }

    /** @return array<string, mixed> */
    private static function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return is_string($value) ? (json_decode($value, true) ?: ['error' => $value]) : [];
    }
}
