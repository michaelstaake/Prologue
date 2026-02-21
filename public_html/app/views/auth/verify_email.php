<div class="bg-zinc-900 p-8 rounded-3xl border border-zinc-700 text-center">
    <h1 class="text-2xl font-bold mb-4">Verify your email</h1>
    <?php
        $toastMessage = '';
        $toastKind = 'info';
        $flashError = flash_get('error');
        $flashSuccess = flash_get('success');
        if ($flashError === 'invalid') {
            $toastMessage = 'Invalid or expired code.';
            $toastKind = 'error';
        } elseif ($flashSuccess === 'sent') {
            $toastMessage = 'Verification code sent to your email.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'resent') {
            $toastMessage = 'A new verification code was sent.';
            $toastKind = 'success';
        }
    ?>
    <?php if ($toastMessage !== ''): ?>
        <div id="page-toast" data-toast-message="<?= htmlspecialchars($toastMessage, ENT_QUOTES, 'UTF-8') ?>" data-toast-kind="<?= htmlspecialchars($toastKind, ENT_QUOTES, 'UTF-8') ?>" class="hidden" aria-hidden="true"></div>
    <?php endif; ?>

    <p class="mb-2 text-zinc-400">Enter the 6-digit code we sent to</p>
    <p class="mb-8 text-zinc-200 font-medium"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></p>

    <form method="POST" action="<?= htmlspecialchars(base_url('/verify-email'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="text" name="code" maxlength="6" placeholder="123456" class="w-64 text-center text-4xl tracking-widest bg-zinc-800 border border-zinc-700 rounded-2xl px-8 py-6" required>
        <button type="submit" class="mt-8 w-full bg-emerald-600 hover:bg-emerald-500 py-4 rounded-2xl font-semibold">Verify email</button>
    </form>

    <form method="POST" action="<?= htmlspecialchars(base_url('/verify-email/resend'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="text-sm text-emerald-400 hover:text-emerald-300">Resend code</button>
    </form>
</div>
