<div class="bg-zinc-900 p-8 rounded-3xl border border-zinc-700">
    <h1 class="text-3xl font-bold text-center mb-7">Reset password</h1>
    <?php
        $toastMessage = '';
        $toastKind = 'info';
        if (flash_get('success') !== null) {
            $toastMessage = 'If that email exists, a reset link has been sent.';
            $toastKind = 'success';
        } elseif (flash_get('error') !== null) {
            $toastMessage = 'Unable to process reset request.';
            $toastKind = 'error';
        }
    ?>
    <?php if ($toastMessage !== ''): ?>
        <div id="page-toast" data-toast-message="<?= htmlspecialchars($toastMessage, ENT_QUOTES, 'UTF-8') ?>" data-toast-kind="<?= htmlspecialchars($toastKind, ENT_QUOTES, 'UTF-8') ?>" class="hidden" aria-hidden="true"></div>
    <?php endif; ?>
    <form method="POST" action="<?= htmlspecialchars(base_url('/forgot-password'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="email" name="email" placeholder="Email" class="w-full bg-zinc-800 border border-zinc-700 rounded-2xl px-5 py-4 mb-6" required>
        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 py-4 rounded-2xl font-semibold">Send reset link</button>
    </form>
    <div class="text-center mt-6">
        <a href="<?= htmlspecialchars(base_url('/login'), ENT_QUOTES, 'UTF-8') ?>" class="text-zinc-400 text-sm">Back to login</a>
    </div>
</div>
