<div class="bg-zinc-900 p-8 rounded-3xl border border-zinc-700">
    <h1 class="text-3xl font-bold text-center mb-3">Install Prologue</h1>
    <p class="text-center text-zinc-400 mb-6">Set up your first admin account and initialize the database.</p>

    <?php if (!empty($errorMessage)): ?>
        <div class="mb-5 rounded-2xl border border-red-700/60 bg-red-950/40 px-4 py-3 text-red-200 text-sm">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($canInstall)): ?>
        <form method="POST" action="<?= htmlspecialchars(base_path('/install'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-4 rounded-2xl border border-zinc-700 bg-zinc-800/70 px-4 py-3 shadow-lg shadow-black/10">
                <label for="install-username" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">Admin username</label>
                <input
                    id="install-username"
                    type="text"
                    name="username"
                    placeholder="Choose a username"
                    class="w-full rounded-2xl border border-zinc-600 bg-zinc-900/80 px-5 py-4 text-zinc-100 placeholder:text-zinc-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                    value="<?= htmlspecialchars($inputUsername ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                    autofocus
                >
            </div>

            <div class="mb-4 rounded-2xl border border-zinc-700 bg-zinc-800/70 px-4 py-3 shadow-lg shadow-black/10">
                <label for="install-email" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-sky-300">Admin email</label>
                <input
                    id="install-email"
                    type="email"
                    name="email"
                    placeholder="Enter an email address"
                    class="w-full rounded-2xl border border-zinc-600 bg-zinc-900/80 px-5 py-4 text-zinc-100 placeholder:text-zinc-500 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                    value="<?= htmlspecialchars($inputEmail ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>

            <div class="mb-6 rounded-2xl border border-zinc-700 bg-zinc-800/70 px-4 py-3 shadow-lg shadow-black/10">
                <label for="install-password" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-amber-300">Admin password</label>
                <input
                    id="install-password"
                    type="password"
                    name="password"
                    placeholder="Create a strong password"
                    class="w-full rounded-2xl border border-zinc-600 bg-zinc-900/80 px-5 py-4 text-zinc-100 placeholder:text-zinc-500 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                    required
                >
            </div>

            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 py-4 rounded-2xl font-semibold">Install</button>
        </form>
    <?php endif; ?>
</div>