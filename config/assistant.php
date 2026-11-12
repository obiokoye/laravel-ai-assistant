<?php

/*
|--------------------------------------------------------------------------
| Shop assistant settings
|--------------------------------------------------------------------------
|
| Pick a provider with LLM_PROVIDER. Each entry maps to a Prism provider and
| names the .env key it needs. If that key is empty (or DEMO_MODE=true) the
| app switches to demo mode and uses the scripted provider instead.
|
*/

$providers = [
    'gemini' => ['prism' => 'gemini', 'key' => 'GEMINI_API_KEY', 'model' => 'gemini-2.5-flash'],
    'groq' => ['prism' => 'groq', 'key' => 'GROQ_API_KEY', 'model' => 'llama-3.3-70b-versatile'],
    'openai' => ['prism' => 'openai', 'key' => 'OPENAI_API_KEY', 'model' => 'gpt-4o-mini'],
    'anthropic' => ['prism' => 'anthropic', 'key' => 'ANTHROPIC_API_KEY', 'model' => 'claude-haiku-4-5'],
];

$name = strtolower((string) env('LLM_PROVIDER', 'gemini'));
$provider = $providers[$name] ?? $providers['gemini'];
$hasKey = filled(env($provider['key']));

return [
    'store_name' => env('STORE_NAME', 'Kora Home'),

    'provider' => array_key_exists($name, $providers) ? $name : 'gemini',
    'prism_provider' => $provider['prism'],
    'model' => env('LLM_MODEL') ?: $provider['model'],
    'key_name' => $provider['key'],
    'providers' => $providers,

    // Demo mode: forced on with DEMO_MODE=true, and automatic when the key is missing.
    'demo' => filter_var(env('DEMO_MODE', false), FILTER_VALIDATE_BOOL) || ! $hasKey,
    // Pause per scripted model turn, so the demo feels like a real model is thinking.
    'demo_delay_ms' => (int) env('DEMO_DELAY_MS', 700),

    // The agent loop: at most this many model calls per user message.
    'max_steps' => (int) env('ASSISTANT_MAX_STEPS', 5),
    // How many earlier chat messages are sent back to the model as context.
    'history' => (int) env('ASSISTANT_HISTORY', 12),
    'timeout' => (int) env('ASSISTANT_TIMEOUT', 60),
];
