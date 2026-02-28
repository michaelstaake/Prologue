<div class="bg-zinc-900 p-8 rounded-3xl border border-zinc-700">
    <h1 class="text-3xl font-bold text-center mb-7">Choose a new password</h1>
    <?php
        $toastMessage = '';
        $toastKind = 'error';
        if (flash_get('error') !== null) {
            $toastMessage = 'Invalid or expired reset token.';
        }
    ?>
    <?php if ($toastMessage !== ''): ?>
        <div id="page-toast" data-toast-message="<?= htmlspecialchars($toastMessage, ENT_QUOTES, 'UTF-8') ?>" data-toast-kind="<?= htmlspecialchars($toastKind, ENT_QUOTES, 'UTF-8') ?>" class="hidden" aria-hidden="true"></div>
    <?php endif; ?>
    <form method="POST" action="<?= htmlspecialchars(base_url('/reset-password'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <label for="reset-password" class="block text-sm text-zinc-300 mb-2">New password</label>
        <input id="reset-password" type="password" name="password" placeholder="New password (8+ chars)" class="w-full bg-zinc-800 border border-zinc-700 rounded-2xl px-5 py-4 mb-6" required>
        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 py-4 rounded-2xl font-semibold">Update password</button>
    </form>
    <div class="text-center mt-6">
        <a href="<?= htmlspecialchars(base_url('/login'), ENT_QUOTES, 'UTF-8') ?>" class="text-zinc-400 text-sm">Back to login</a>
    </div>
</div>
