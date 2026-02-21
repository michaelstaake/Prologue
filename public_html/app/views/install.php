<div class="bg-zinc-900 p-8 rounded-3xl border border-zinc-700">
    <h1 class="text-3xl font-bold text-center mb-3">Install Prologue</h1>
    <p class="text-center text-zinc-400 mb-6">Set up your first admin account and initialize the database.</p>

    <?php if (!empty($errorMessage)): ?>
        <div class="mb-5 rounded-2xl border border-red-700/60 bg-red-950/40 px-4 py-3 text-red-200 text-sm">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($canInstall)): ?>
        <form method="POST" action="<?= htmlspecialchars(base_url('/install'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <input
                type="text"
                name="username"
                placeholder="Admin username"
                class="w-full bg-zinc-800 border border-zinc-700 rounded-2xl px-5 py-4 mb-4"
                value="<?= htmlspecialchars($inputUsername ?? '', ENT_QUOTES, 'UTF-8') ?>"
                required
                autofocus
            >

            <input
                type="email"
                name="email"
                placeholder="Admin email"
                class="w-full bg-zinc-800 border border-zinc-700 rounded-2xl px-5 py-4 mb-4"
                value="<?= htmlspecialchars($inputEmail ?? '', ENT_QUOTES, 'UTF-8') ?>"
                required
            >

            <input
                type="password"
                name="password"
                placeholder="Admin password"
                class="w-full bg-zinc-800 border border-zinc-700 rounded-2xl px-5 py-4 mb-6"
                required
            >

            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 py-4 rounded-2xl font-semibold">Install</button>
        </form>
    <?php endif; ?>
</div>