<div class="p-8 overflow-auto">
    <div class="max-w-lg mx-auto bg-zinc-900 border border-zinc-700 rounded-2xl p-6">
        <h1 class="text-2xl font-semibold text-zinc-100">Delete personal chat</h1>
        <p class="mt-3 text-sm text-zinc-400">Are you sure you want to permanently delete this chat?</p>

        <div class="mt-4 rounded-xl border border-zinc-700 bg-zinc-800/70 px-4 py-3">
            <div class="text-xs uppercase tracking-wide text-zinc-500">Chat</div>
            <div class="mt-1 text-zinc-200 font-medium"><?= htmlspecialchars((string)($chatTitle ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="mt-0.5 text-xs text-zinc-400"><?= htmlspecialchars((string)($chatNumberFormatted ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <a href="<?= htmlspecialchars(base_url('/c/' . (string)($chatNumberFormatted ?? '')), ENT_QUOTES, 'UTF-8') ?>" class="px-4 py-2 rounded-xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-200">Cancel</a>
            <form method="post" action="<?= htmlspecialchars(base_url('/c/' . (string)($chatNumberFormatted ?? '') . '/delete'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="px-4 py-2 rounded-xl bg-red-700 hover:bg-red-600 text-white">Delete chat</button>
            </form>
        </div>
    </div>
</div>
