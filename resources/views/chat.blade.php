<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $store }} · Ops assistant</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/assistant.css') }}">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="logo">K</div>
            <div>
                <div class="brand-name">{{ $store }}</div>
                <div class="brand-sub">Ops assistant</div>
            </div>
        </div>

        <div class="status {{ $demo ? 'status-demo' : 'status-live' }}">
            <span class="dot"></span>
            @if ($demo)
                Demo mode · scripted replies
            @else
                {{ ucfirst($provider) }} · {{ $model }}
            @endif
        </div>

        <section class="panel">
            <h2>Tools the agent can call</h2>
            <ul class="tool-list">
                @foreach ($tools as $tool)
                    <li>
                        <code>{{ $tool['name'] }}</code>
                        <span>{{ \Illuminate\Support\Str::before($tool['description'], '.') }}.</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="panel">
            <h2>Restock requests <span class="hint">need manager approval</span></h2>
            <ul class="restock-list" id="restock-list" data-initial='@json($restockRequests)'></ul>
        </section>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="mobile-brand">
                <div class="logo small">K</div>
                <div class="brand-name">{{ $store }}</div>
            </div>
            <div class="topbar-title">
                <h1>Ask about stock, orders and sales</h1>
                <p>The assistant looks things up with tools before it answers.</p>
            </div>
            <button type="button" class="btn-ghost" id="new-chat">New chat</button>
        </header>

        @if ($demo)
            <div class="demo-banner" role="status">
                <strong>Demo mode: scripted AI replies.</strong>
                The tools and database are real. Add a <code>GEMINI_API_KEY</code> to <code>.env</code> for a live model.
            </div>
        @endif

        <div class="messages" id="messages" aria-live="polite">
            <div class="empty" id="empty">
                <div class="empty-icon">✦</div>
                <h2>What do you need to know?</h2>
                <p>Try one of these:</p>
                <div class="samples">
                    @foreach ($samples as $sample)
                        <button type="button" class="sample">{{ $sample }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        <form class="composer" id="composer" autocomplete="off">
            <input id="input" name="message" maxlength="1000" placeholder="Ask about stock, orders, sales…" aria-label="Message">
            <button type="submit" id="send" aria-label="Send">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M3.4 20.4 21 12 3.4 3.6 3.4 10l12.6 2-12.6 2z"/></svg>
            </button>
        </form>
    </main>
</div>
<script src="{{ asset('js/assistant.js') }}"></script>
</body>
</html>
