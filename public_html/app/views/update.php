<?php
$title = 'Update Prologue';
ob_start();
?>
<div class="w-full max-w-md bg-zinc-900 p-8 rounded-3xl border border-zinc-700">
    <?php $lockRemaining = isset($lockRemaining) ? max(0, (int)$lockRemaining) : 0; ?>
    <?php $isLocked = $lockRemaining > 0; ?>
    <h1 class="text-3xl font-bold text-center mb-2">Update Prologue</h1>
    <p class="text-center text-zinc-400 mb-6">Run database migrations to bring your installation up to date.</p>

    <?php if (!empty($errorMessage)): ?>
        <div class="mb-5 rounded-2xl border border-red-700/60 bg-red-950/40 px-4 py-3 text-red-200 text-sm">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($isLocked): ?>
        <div class="mb-5 rounded-2xl border border-amber-700/60 bg-amber-950/30 px-4 py-3 text-amber-200 text-sm">
            Update is temporarily locked because someone just clicked run. Please wait about <span id="update-lock-seconds" data-seconds="<?= htmlspecialchars((string)$lockRemaining, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$lockRemaining, ENT_QUOTES, 'UTF-8') ?></span> seconds.
        </div>
    <?php endif; ?>

    <div class="mb-6 rounded-2xl border border-zinc-700 bg-zinc-800/50 px-5 py-4 text-sm space-y-2">
        <div class="flex justify-between">
            <span class="text-zinc-400">Current database version</span>
            <span class="font-mono text-red-300"><?= htmlspecialchars($dbVersion, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="flex justify-between">
            <span class="text-zinc-400">App version</span>
            <span class="font-mono text-emerald-400"><?= htmlspecialchars($appVersion, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <form method="POST" action="<?= htmlspecialchars(base_url('/update'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" <?= $isLocked ? 'disabled aria-disabled="true"' : '' ?> class="w-full py-4 rounded-2xl font-semibold transition <?= $isLocked ? 'bg-zinc-700 text-zinc-300 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-500' ?>">Run Update</button>
    </form>
</div>
<?php if ($isLocked): ?>
<script>
    (function() {
        var secondsElement = document.getElementById('update-lock-seconds');
        if (!secondsElement) {
            return;
        }

        var remaining = parseInt(secondsElement.getAttribute('data-seconds') || '0', 10);
        if (!Number.isFinite(remaining) || remaining <= 0) {
            window.location.reload();
            return;
        }

        var timer = window.setInterval(function() {
            remaining -= 1;
            if (remaining <= 0) {
                window.clearInterval(timer);
                window.location.reload();
                return;
            }

            secondsElement.textContent = String(remaining);
        }, 1000);
    })();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/standalone.php';
