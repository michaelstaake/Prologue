<div class="bg-zinc-900 p-8 rounded-3xl border border-zinc-700">
    <h1 class="text-3xl font-bold text-center mb-7">Create account</h1>
    <?php
        $toastMessage = '';
        $toastKind = 'error';
        $flashError = flash_get('error');
        if ($flashError === 'username_taken') {
            $toastMessage = 'Username is already taken.';
        } elseif ($flashError === 'too_many_attempts') {
            $toastMessage = 'Too many attempts - please try again in a few minutes.';
        } elseif ($flashError === 'invalid') {
            $toastMessage = 'Username must be 4-32 characters, start with a letter, and use only lowercase letters and numbers.';
        } elseif ($flashError === 'email_taken') {
            $toastMessage = 'Email is already in use.';
        } elseif ($flashError === 'invalid_invite') {
            $toastMessage = 'Invalid or already used invite code.';
        } elseif ($flashError !== null) {
            $toastMessage = 'Registration failed. Check invite code and form values.';
        }
    ?>
    <?php if ($toastMessage !== ''): ?>
        <div id="page-toast" data-toast-message="<?= htmlspecialchars($toastMessage, ENT_QUOTES, 'UTF-8') ?>" data-toast-kind="<?= htmlspecialchars($toastKind, ENT_QUOTES, 'UTF-8') ?>" class="hidden" aria-hidden="true"></div>
    <?php endif; ?>
    <form method="POST" action="<?= htmlspecialchars(base_url('/register'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <label for="register-username" class="block text-sm text-zinc-300 mb-2">Username</label>
        <input id="register-username" type="text" name="username" placeholder="Username" minlength="4" maxlength="32" pattern="[a-z][a-z0-9]{3,31}" title="Username must be 4-32 characters, start with a lowercase letter, and contain only lowercase letters and numbers." autocapitalize="none" spellcheck="false" aria-describedby="username-requirements" class="w-full bg-zinc-800 border border-zinc-700 rounded-2xl px-5 py-4" required>
        <p id="username-requirements" class="text-xs text-zinc-400 mt-2 mb-4">Username must be 4-32 characters, contain only lowercase letters and numbers, and cannot start with a number.</p>
        <label for="register-email" class="block text-sm text-zinc-300 mb-2">Email</label>
        <input id="register-email" type="email" name="email" placeholder="Email" class="w-full bg-zinc-800 border border-zinc-700 rounded-2xl px-5 py-4 mb-4" required>
        <label for="register-password" class="block text-sm text-zinc-300 mb-2">Password</label>
        <input id="register-password" type="password" name="password" placeholder="Password (8+ chars)" class="w-full bg-zinc-800 border border-zinc-700 rounded-2xl px-5 py-4 mb-4" required>
        <?php if (!isset($invitesEnabled) || $invitesEnabled): ?>
            <label for="register-invite-code" class="block text-sm text-zinc-300 mb-2">Invite code</label>
            <input id="register-invite-code" type="text" name="invite_code" placeholder="Invite code (1234-5678)" class="w-full bg-zinc-800 border border-zinc-700 rounded-2xl px-5 py-4 mb-6" required>
        <?php endif; ?>
        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 py-4 rounded-2xl font-semibold">Register</button>
    </form>
</div>
</div>