<div class="p-8 overflow-auto space-y-8 max-w-3xl">

    <div class="flex items-center gap-4">
        <a href="<?= htmlspecialchars(base_url('/apikeys')) ?>"
           class="inline-flex items-center gap-1 text-sm text-zinc-400 hover:text-zinc-200 transition">
            <i class="fa-solid fa-arrow-left"></i>
            Back to API Keys
        </a>
    </div>

    <h1 class="text-3xl font-bold">Bot API Documentation</h1>
    <p class="text-zinc-400">Use Bot API keys to send messages to group chats you own from external applications, scripts, or services. User keys will have more access but will come later.</p>

    <!-- Authentication -->
    <section class="space-y-3">
        <h2 class="text-xl font-semibold text-zinc-100">Authentication</h2>
        <p class="text-sm text-zinc-300">
            Include your API key in the request as a <code class="rounded bg-zinc-800 px-1.5 py-0.5 text-emerald-400">api_key</code> POST parameter,
            or as an <code class="rounded bg-zinc-800 px-1.5 py-0.5 text-emerald-400">X-API-Key</code> HTTP header.
        </p>
        <p class="text-sm text-zinc-400">
            API keys are 64-character hexadecimal strings. Keep them secret — anyone with your key can perform actions as you.
        </p>
    </section>

    <!-- Send Message -->
    <section class="space-y-4">
        <h2 class="text-xl font-semibold text-zinc-100">Send Message</h2>

        <div class="rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3">
            <span class="inline-block rounded bg-emerald-600 px-2 py-0.5 text-xs font-bold text-white mr-2">POST</span>
            <code class="text-sm text-zinc-100">/api/bot/send</code>
        </div>

        <h3 class="text-lg font-medium text-zinc-200">Parameters</h3>
        <div class="overflow-x-auto rounded-xl border border-zinc-700">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-700 bg-zinc-800/50">
                        <th class="px-4 py-3 text-left font-medium text-zinc-300">Parameter</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-300">Type</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-300">Required</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-300">Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    <tr>
                        <td class="px-4 py-3"><code class="text-emerald-400">api_key</code></td>
                        <td class="px-4 py-3 text-zinc-400">string</td>
                        <td class="px-4 py-3"><span class="text-emerald-400">Yes</span></td>
                        <td class="px-4 py-3 text-zinc-300">Your 64-character API key. Can also be sent via <code class="text-emerald-400">X-API-Key</code> header.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3"><code class="text-emerald-400">chat_number</code></td>
                        <td class="px-4 py-3 text-zinc-400">string</td>
                        <td class="px-4 py-3"><span class="text-emerald-400">Yes</span></td>
                        <td class="px-4 py-3 text-zinc-300">The 16-digit chat number of the target group chat. Dashes are optional (e.g. <code class="text-zinc-400">1234-5678-9012-3456</code> or <code class="text-zinc-400">1234567890123456</code>).</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3"><code class="text-emerald-400">text</code></td>
                        <td class="px-4 py-3 text-zinc-400">string</td>
                        <td class="px-4 py-3"><span class="text-emerald-400">Yes</span></td>
                        <td class="px-4 py-3 text-zinc-300">The message content. Maximum 16,384 characters.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h3 class="text-lg font-medium text-zinc-200">Example Request</h3>
        <div class="rounded-xl border border-zinc-700 bg-zinc-900 p-4 overflow-x-auto">
            <pre class="text-sm text-zinc-300 whitespace-pre"><code>curl -X POST <?= htmlspecialchars(rtrim(base_url(''), '/')) ?>/api/bot/send \
  -d "api_key=YOUR_API_KEY" \
  -d "chat_number=1234-5678-9012-3456" \
  -d "text=Hello from the bot!"</code></pre>
        </div>

        <p class="text-sm text-zinc-400">Or using the header for authentication:</p>
        <div class="rounded-xl border border-zinc-700 bg-zinc-900 p-4 overflow-x-auto">
            <pre class="text-sm text-zinc-300 whitespace-pre"><code>curl -X POST <?= htmlspecialchars(rtrim(base_url(''), '/')) ?>/api/bot/send \
  -H "X-API-Key: YOUR_API_KEY" \
  -d "chat_number=1234-5678-9012-3456" \
  -d "text=Hello from the bot!"</code></pre>
        </div>

        <h3 class="text-lg font-medium text-zinc-200">Success Response</h3>
        <div class="rounded-xl border border-zinc-700 bg-zinc-900 p-4">
            <pre class="text-sm text-zinc-300"><code>{
  "success": true,
  "message_id": 123
}</code></pre>
        </div>

        <h3 class="text-lg font-medium text-zinc-200">Error Response</h3>
        <div class="rounded-xl border border-zinc-700 bg-zinc-900 p-4">
            <pre class="text-sm text-zinc-300"><code>{
  "error": "Description of the error"
}</code></pre>
        </div>
    </section>

    <!-- Error Codes -->
    <section class="space-y-3">
        <h2 class="text-xl font-semibold text-zinc-100">Error Codes</h2>
        <div class="overflow-x-auto rounded-xl border border-zinc-700">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-700 bg-zinc-800/50">
                        <th class="px-4 py-3 text-left font-medium text-zinc-300">HTTP Status</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-300">Meaning</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    <tr>
                        <td class="px-4 py-3"><code class="text-red-400">400</code></td>
                        <td class="px-4 py-3 text-zinc-300">Bad request — missing or invalid parameters (e.g. empty text, text too long, missing chat_number).</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3"><code class="text-red-400">401</code></td>
                        <td class="px-4 py-3 text-zinc-300">Unauthorized — invalid or missing API key.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3"><code class="text-red-400">403</code></td>
                        <td class="px-4 py-3 text-zinc-300">Forbidden — API key is expired, account is banned, IP not allowed, chat not authorized, or you no longer own the chat.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3"><code class="text-red-400">404</code></td>
                        <td class="px-4 py-3 text-zinc-300">Not found — the specified chat does not exist or has been deleted.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3"><code class="text-red-400">429</code></td>
                        <td class="px-4 py-3 text-zinc-300">Rate limited — too many requests. Wait and try again.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Notes -->
    <section class="space-y-3">
        <h2 class="text-xl font-semibold text-zinc-100">Notes</h2>
        <ul class="list-disc list-inside text-sm text-zinc-300 space-y-2">
            <li>Bot keys can only send messages to <strong>group chats that you own</strong>. If chat ownership is transferred, the bot key will lose access to that chat.</li>
            <li>Messages sent via the Bot API appear as if sent by the API key owner.</li>
            <li>If you set IP restrictions on your API key, only requests from those IP addresses will be accepted.</li>
            <li>All API key usage is logged with IP address and timestamp.</li>
            <li>Expired keys remain visible in your API Keys page for up to one year before being automatically removed.</li>
            <li>If your account is banned, all API keys are immediately expired.</li>
        </ul>
    </section>

</div>
