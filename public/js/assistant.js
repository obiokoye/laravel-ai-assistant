// Chat UI: posts each message to /chat and renders the reply plus a card for
// every tool call the agent made. All model text is HTML-escaped first.
(() => {
    const $ = (sel) => document.querySelector(sel);
    const messages = $('#messages');
    const form = $('#composer');
    const input = $('#input');
    const send = $('#send');
    const restockList = $('#restock-list');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let conversationId = null;
    let busy = false;

    const ICONS = {
        search_products: '🔎',
        low_stock_report: '📦',
        get_order: '🧾',
        sales_summary: '📈',
        create_restock_request: '📝',
    };
    const WRITE_TOOLS = ['create_restock_request'];

    const naira = (n) => '₦' + Number(n || 0).toLocaleString('en-NG');

    const escapeHtml = (s) => String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    // Tiny, safe markdown: **bold**, `code`, "- " lists and paragraphs.
    function renderText(text) {
        const inline = (s) => escapeHtml(s)
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/`([^`]+)`/g, '<code>$1</code>');
        const blocks = String(text).trim().split(/\n{2,}/);

        return blocks.map((block) => {
            const lines = block.split('\n');
            const items = lines.filter((l) => /^\s*[-*•]\s+/.test(l));
            if (items.length && items.length === lines.length) {
                return '<ul>' + items.map((l) => '<li>' + inline(l.replace(/^\s*[-*•]\s+/, '')) + '</li>').join('') + '</ul>';
            }
            if (items.length) {
                const head = lines.filter((l) => !/^\s*[-*•]\s+/.test(l));
                return '<p>' + head.map(inline).join('<br>') + '</p><ul>'
                    + items.map((l) => '<li>' + inline(l.replace(/^\s*[-*•]\s+/, '')) + '</li>').join('') + '</ul>';
            }
            return '<p>' + lines.map(inline).join('<br>') + '</p>';
        }).join('');
    }

    function summarise(call) {
        const r = call.result || {};
        if (typeof r !== 'object') return { text: String(r).slice(0, 80), cls: '' };
        if (r.error) return { text: r.error, cls: 'bad' };
        switch (call.name) {
            case 'search_products': return { text: `${r.count} match${r.count === 1 ? '' : 'es'}`, cls: '' };
            case 'low_stock_report': return { text: `${r.count} low · ${r.out_of_stock} out of stock`, cls: '' };
            case 'get_order': return { text: `#${r.number} · ${r.status} · ${naira(r.total)}`, cls: '' };
            case 'sales_summary': return { text: `${r.orders} orders · ${naira(r.revenue)}`, cls: '' };
            case 'create_restock_request':
                return r.created
                    ? { text: `Request #${r.request.id} filed · pending approval`, cls: 'ok' }
                    : { text: 'Already pending', cls: '' };
            default: return { text: 'done', cls: '' };
        }
    }

    function toolCard(call) {
        const args = Object.entries(call.args || {})
            .map(([k, v]) => `${escapeHtml(k)}: ${escapeHtml(JSON.stringify(v))}`).join(', ');
        const sum = summarise(call);
        const el = document.createElement('details');
        el.className = 'tool' + (WRITE_TOOLS.includes(call.name) ? ' write' : '') + (sum.cls === 'bad' ? ' failed' : '');
        el.innerHTML = `
            <summary>
                <span class="t-icon">${ICONS[call.name] || '🔧'}</span>
                <span class="t-call"><b>${escapeHtml(call.name)}</b>(${args})</span>
                <span class="t-sum ${sum.cls}">${escapeHtml(sum.text)}</span>
                <span class="t-step">step ${call.step}</span>
            </summary>
            <pre>${escapeHtml(JSON.stringify(call.result, null, 2))}</pre>`;
        return el;
    }

    function addMessage(role, html) {
        $('#empty')?.remove();
        const wrap = document.createElement('div');
        wrap.className = `msg ${role}`;
        wrap.innerHTML = html;
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;
        return wrap;
    }

    function renderRestock(list, freshIds = []) {
        if (!restockList) return;
        restockList.innerHTML = '';
        if (!list.length) {
            restockList.innerHTML = '<li class="empty-note">None yet. Ask the assistant to raise one.</li>';
            return;
        }
        for (const r of list) {
            const li = document.createElement('li');
            if (freshIds.includes(r.id)) li.className = 'new';
            li.innerHTML = `<div><div class="r-name">${escapeHtml(r.product)}</div>
                <div class="r-sub">${escapeHtml(r.sku)} · ${escapeHtml(r.quantity)} units · ${escapeHtml(r.created || '')}</div></div>
                <span class="pill">${escapeHtml(r.status)}</span>`;
            restockList.appendChild(li);
        }
    }

    async function ask(text) {
        if (busy || !text.trim()) return;
        busy = true;
        send.disabled = true;
        input.value = '';

        addMessage('user', `<div class="bubble">${escapeHtml(text)}</div>`);
        const pending = addMessage('assistant',
            '<div class="bubble"><span class="thinking"><span class="dots"><i></i><i></i><i></i></span>Checking the shop data…</span></div>');

        try {
            const res = await fetch('/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ message: text, conversation_id: conversationId }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.error || data.message || `Request failed (${res.status})`);

            conversationId = data.conversation_id;
            const reply = data.reply;
            pending.innerHTML = '';

            if (reply.tool_calls.length) {
                const tools = document.createElement('div');
                tools.className = 'tools';
                reply.tool_calls.forEach((c) => tools.appendChild(toolCard(c)));
                pending.appendChild(tools);
            }

            const bubble = document.createElement('div');
            bubble.className = 'bubble';
            bubble.innerHTML = renderText(reply.content);
            pending.appendChild(bubble);

            const meta = document.createElement('div');
            meta.className = 'meta';
            const calls = reply.tool_calls.length;
            meta.textContent = `${reply.steps} step${reply.steps === 1 ? '' : 's'} · ${calls} tool call${calls === 1 ? '' : 's'}`
                + (reply.tokens ? ` · ${reply.tokens.toLocaleString()} tokens` : '');
            pending.appendChild(meta);

            const fresh = reply.tool_calls
                .filter((c) => c.name === 'create_restock_request' && c.result && c.result.created)
                .map((c) => c.result.request.id);
            renderRestock(data.restock_requests || [], fresh);
        } catch (err) {
            pending.innerHTML = `<div class="bubble error">${escapeHtml(err.message)}</div>`;
        } finally {
            busy = false;
            send.disabled = false;
            input.focus();
            messages.scrollTop = messages.scrollHeight;
        }
    }

    form.addEventListener('submit', (e) => { e.preventDefault(); ask(input.value); });
    document.querySelectorAll('.sample').forEach((b) => b.addEventListener('click', () => ask(b.textContent.trim())));
    $('#new-chat').addEventListener('click', () => window.location.reload());

    renderRestock(JSON.parse(restockList?.dataset.initial || '[]'));
})();
