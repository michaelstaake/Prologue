<?php
$title = 'Update Required — Prologue';
ob_start();
?>
<div class="w-full max-w-md bg-zinc-900 p-8 rounded-3xl border border-zinc-700 text-center">
    <h1 class="text-2xl font-bold mb-2">Database Update Required</h1>
    <p class="text-zinc-400 mb-6">
        This installation has been updated to a new version. To continue using Prologue, please run the database update process.
    </p>
    <?php if ($showUpdateButton ?? false): ?>
    <a href="<?= htmlspecialchars(base_url('/update'), ENT_QUOTES, 'UTF-8') ?>" class="inline-block w-full bg-emerald-600 hover:bg-emerald-500 py-4 rounded-2xl font-semibold transition">
        Run Database Update
    </a>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/standalone.php';
