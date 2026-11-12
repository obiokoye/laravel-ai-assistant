<?php

namespace App\Providers;

use App\Ai\Demo\DemoScripts;
use App\Ai\Demo\ScriptedProvider;
use Illuminate\Support\ServiceProvider;
use Prism\Prism\PrismManager;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Demo mode (and the tests) use a scripted "model" registered as a
        // normal Prism provider, so the agent code is identical either way.
        $this->app->make(PrismManager::class)->extend(
            'scripted',
            fn () => new ScriptedProvider(DemoScripts::all(), (int) config('assistant.demo_delay_ms')),
        );
    }
}
