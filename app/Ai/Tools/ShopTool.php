<?php

namespace App\Ai\Tools;

use Prism\Prism\Tool;

/**
 * Base class for the assistant's tools. Prism calls __invoke() with the
 * arguments the model chose; we always answer with a JSON string the model
 * can read. Errors are returned as {"error": "..."} so the model can recover.
 */
abstract class ShopTool extends Tool
{
    /** @param  array<string, mixed>  $data */
    protected function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    protected function error(string $message): string
    {
        return $this->json(['error' => $message]);
    }
}
