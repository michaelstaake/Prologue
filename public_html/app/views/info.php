<div class="p-8 overflow-auto space-y-6">
    <h1 class="text-3xl font-bold">System</h1>
    <div class="flex items-center gap-4">
        <a href="https://prologue.chat" target="_blank" class="inline-flex items-center gap-1.5 text-sm text-zinc-400 hover:text-zinc-100 transition-colors">Prologue.chat <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i></a>
        <a href="https://github.com/michaelstaake/Prologue/issues" target="_blank" class="inline-flex items-center gap-1.5 text-sm text-zinc-400 hover:text-zinc-100 transition-colors">Report Problems on GitHub <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i></a>
    </div>

    <section id="browser-permissions-section" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-2xl">
        <h2 class="text-xl font-semibold mb-4">Browser Permissions</h2>
        <p class="text-sm text-zinc-400 mb-5">This applies to your current browser/device only.</p>
        <div class="space-y-3" id="browser-permissions-list">
            <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                <span class="text-zinc-100">Sound</span>
                <div class="flex items-center gap-2">
                    <button type="button" data-browser-permission-test="sound" class="hidden px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Test</button>
                    <span data-browser-permission-status="sound" class="text-xs px-2.5 py-1 rounded-full border border-zinc-600 bg-zinc-700/50 text-zinc-200">Checking…</span>
                </div>
            </div>
            <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                <span class="text-zinc-100">Webcam</span>
                <div class="flex items-center gap-2">
                    <button type="button" data-browser-permission-test="camera" class="hidden px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Test</button>
                    <span data-browser-permission-status="camera" class="text-xs px-2.5 py-1 rounded-full border border-zinc-600 bg-zinc-700/50 text-zinc-200">Checking…</span>
                </div>
            </div>
            <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                <span class="text-zinc-100">Microphone</span>
                <div class="flex items-center gap-2">
                    <button type="button" data-browser-permission-test="microphone" class="hidden px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Test</button>
                    <span data-browser-permission-status="microphone" class="text-xs px-2.5 py-1 rounded-full border border-zinc-600 bg-zinc-700/50 text-zinc-200">Checking…</span>
                </div>
            </div>
            <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                <span class="text-zinc-100">Screensharing</span>
                <div class="flex items-center gap-2">
                    <button type="button" data-browser-permission-test="screenshare" class="hidden px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Test</button>
                    <span data-browser-permission-status="screenshare" class="text-xs px-2.5 py-1 rounded-full border border-zinc-600 bg-zinc-700/50 text-zinc-200">Checking…</span>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-2xl">
        <h2 class="text-xl font-semibold mb-4">Licensing and Attribution</h2>

        <div class="space-y-3 text-zinc-200">
            <div class="border border-zinc-700 bg-zinc-800/40 rounded-xl px-4 py-3">
                <span class="text-zinc-400">Open source license</span>
                <div class="font-semibold mt-1">GPL-3.0</div>
            </div>

            <div class="border border-zinc-700 bg-zinc-800/40 rounded-xl px-4 py-3">
                <span class="text-zinc-400">Front end framework</span>
                <div class="font-semibold mt-1">Tailwind</div>
            </div>

            <div class="border border-zinc-700 bg-zinc-800/40 rounded-xl px-4 py-3">
                <span class="text-zinc-400">Icon font</span>
                <div class="font-semibold mt-1">Font Awesome Free</div>
            </div>

            <div class="border border-zinc-700 bg-zinc-800/40 rounded-xl px-4 py-3">
                <span class="text-zinc-400">Emojis</span>
                <div class="font-semibold mt-1">OpenMoji</div>
            </div>
        </div>
    </section>

</div>