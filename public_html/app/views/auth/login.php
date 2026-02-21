<div class="bg-zinc-900 p-8 rounded-3xl border border-zinc-700">
    <p class="text-center text-zinc-400 mb-6">This app is the Prologue, and you and your friends create the story. Enjoy!</p>
    <?php
        $toastMessage = '';
        $toastKind = 'info';
        $flashError = flash_get('error');
        $flashSuccess = flash_get('success');
        if ($flashError === 'banned') {
            $toastMessage = 'Your account has been banned.';
            $toastKind = 'error';
        } elseif ($flashError === 'too_many_attempts') {
            $toastMessage = 'Too many attempts - please try again in a few minutes.';
            $toastKind = 'error';
        } elseif ($flashError !== null) {
            $toastMessage = 'Login failed. Check your credentials.';
            $toastKind = 'error';
        } elseif ($flashSuccess === 'installed') {
            $toastMessage = 'Installation complete. You can now log in.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'reset') {
            $toastMessage = 'Password reset successfully. Log in with your new password.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'session_exited') {
            $toastMessage = 'Session ended successfully.';
            $toastKind = 'success';
        }
    ?>
    <?php if ($toastMessage !== ''): ?>
        <div id="page-toast" data-toast-message="<?= htmlspecialchars($toastMessage, ENT_QUOTES, 'UTF-8') ?>" data-toast-kind="<?= htmlspecialchars($toastKind, ENT_QUOTES, 'UTF-8') ?>" class="hidden" aria-hidden="true"></div>
    <?php endif; ?>
    <form method="POST" action="<?= htmlspecialchars(base_url('/login'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="email" name="email" placeholder="Email" class="w-full bg-zinc-800 border border-zinc-700 rounded-2xl px-5 py-4 mb-4" required autofocus>
        <input type="password" name="password" placeholder="Password" class="w-full bg-zinc-800 border border-zinc-700 rounded-2xl px-5 py-4 mb-6" required>
        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 py-4 rounded-2xl font-semibold">Log in</button>
    </form>
    <div class="text-center mt-6">
        <a href="<?= htmlspecialchars(base_url('/register'), ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-400">Create account</a><br>
        <a href="<?= htmlspecialchars(base_url('/forgot-password'), ENT_QUOTES, 'UTF-8') ?>" class="text-zinc-400 text-sm">Forgot password?</a>
    </div>
</div>