<div class="bg-zinc-900 p-8 rounded-3xl border border-zinc-700 text-center">
    <h1 class="text-2xl font-bold mb-6">Two-factor authentication</h1>
    <?php
        $toastMessage = '';
        $toastKind = 'error';
        if (flash_get('error') !== null) {
            $toastMessage = 'Invalid or expired code.';
        }
    ?>
    <?php if ($toastMessage !== ''): ?>
        <div id="page-toast" data-toast-message="<?= htmlspecialchars($toastMessage, ENT_QUOTES, 'UTF-8') ?>" data-toast-kind="<?= htmlspecialchars($toastKind, ENT_QUOTES, 'UTF-8') ?>" class="hidden" aria-hidden="true"></div>
    <?php endif; ?>
    <p class="mb-8 text-zinc-400">Enter the 6-digit code<?php if (($providerLabel ?? '') === 'Email'): ?> sent to your email<?php endif; ?></p>
    <form method="POST" action="<?= htmlspecialchars(base_url('/2fa'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="text" name="code" maxlength="6" placeholder="123456" class="w-64 text-center text-4xl tracking-widest bg-zinc-800 border border-zinc-700 rounded-2xl px-8 py-6" required>
        <button type="submit" class="mt-8 w-full bg-emerald-600 hover:bg-emerald-500 py-4 rounded-2xl font-semibold">Verify</button>
    </form>
</div>