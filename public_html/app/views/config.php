<div class="p-8 overflow-auto space-y-6">
    <h1 class="text-3xl font-bold">Config</h1>

    <?php
        $toastMessage = '';
        $toastKind = 'info';

        $flashSuccess = flash_get('success');
        $flashError = flash_get('error');
        $mailTestError = flash_get('mail_test_error');

        if ($flashSuccess === 'mail_saved') {
            $toastMessage = 'Mail settings saved.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'mail_test_sent') {
            $toastMessage = 'Test email sent — check your inbox to confirm it arrived.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'accounts_saved') {
            $toastMessage = 'Account settings saved.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'more_saved') {
            $toastMessage = 'Settings saved.';
            $toastKind = 'success';
        }
    ?>

    <?php if ($toastMessage !== ''): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                showToast(<?= json_encode($toastMessage) ?>, <?= json_encode($toastKind) ?>);
            });
        </script>
    <?php endif; ?>

    <?php if (!empty($mailTestError)): ?>
        <div class="max-w-2xl rounded-xl border border-red-700 bg-red-950/60 px-5 py-4">
            <p class="text-sm font-semibold text-red-300 mb-1">Test email failed</p>
            <p class="text-sm text-red-200 break-words"><?= htmlspecialchars($mailTestError, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    <?php endif; ?>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-2xl">
        <h2 class="text-xl font-semibold mb-1">Mail</h2>
        <p class="text-sm text-zinc-400 mb-5">SMTP settings used to send verification and notification emails. Leave the password field blank to keep the current password.</p>

        <form method="POST" action="<?= htmlspecialchars(base_url('/config/mail'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label for="mail_host" class="block text-sm text-zinc-400 mb-1">Host</label>
                    <input
                        type="text"
                        id="mail_host"
                        name="mail_host"
                        value="<?= htmlspecialchars((string)($mail_host ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="smtp.example.com"
                        autocomplete="off"
                        class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100"
                    >
                </div>

                <div>
                    <label for="mail_port" class="block text-sm text-zinc-400 mb-1">Port</label>
                    <input
                        type="number"
                        id="mail_port"
                        name="mail_port"
                        value="<?= htmlspecialchars((string)($mail_port ?? '587'), ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="587"
                        min="1"
                        max="65535"
                        class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100"
                    >
                </div>
            </div>

            <div>
                <label for="mail_user" class="block text-sm text-zinc-400 mb-1">Username</label>
                <input
                    type="text"
                    id="mail_user"
                    name="mail_user"
                    value="<?= htmlspecialchars((string)($mail_user ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="user@example.com"
                    autocomplete="off"
                    class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100"
                >
            </div>

            <div>
                <label for="mail_pass" class="block text-sm text-zinc-400 mb-1">Password</label>
                <input
                    type="password"
                    id="mail_pass"
                    name="mail_pass"
                    value=""
                    placeholder="Leave blank to keep current password"
                    autocomplete="new-password"
                    class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100"
                >
            </div>

            <div>
                <label for="mail_from" class="block text-sm text-zinc-400 mb-1">From address</label>
                <input
                    type="text"
                    id="mail_from"
                    name="mail_from"
                    value="<?= htmlspecialchars((string)($mail_from ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="noreply@example.com"
                    autocomplete="off"
                    class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100"
                >
            </div>

            <div>
                <label for="mail_from_name" class="block text-sm text-zinc-400 mb-1">From name</label>
                <input
                    type="text"
                    id="mail_from_name"
                    name="mail_from_name"
                    value="<?= htmlspecialchars((string)($mail_from_name ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="My App"
                    autocomplete="off"
                    class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100"
                >
            </div>

            <div class="pt-2 flex flex-wrap items-center gap-3">
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-medium transition">Save mail settings</button>
            </div>
        </form>

        <div class="mt-5 pt-5 border-t border-zinc-700">
            <p class="text-sm text-zinc-400 mb-3">Send a test email to your account address to verify the settings above are working.</p>
            <form method="POST" action="<?= htmlspecialchars(base_url('/config/mail/test'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-zinc-700 hover:bg-zinc-600 text-zinc-100 rounded-xl font-medium transition">
                    <i class="fa-regular fa-paper-plane"></i>
                    Send test email
                </button>
            </form>
        </div>
    </section>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-2xl">
        <h2 class="text-xl font-semibold mb-1">Accounts</h2>
        <p class="text-sm text-zinc-400 mb-5">Control how new accounts are created and verified.</p>

        <form method="POST" action="<?= htmlspecialchars(base_url('/config/accounts'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <label class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 cursor-pointer">
                <div>
                    <span class="block text-zinc-100">Require invite code to register</span>
                    <span class="block text-xs text-zinc-500 mt-0.5">New accounts must provide a valid invite code.</span>
                </div>
                <input type="checkbox" name="invite_code_required" value="1" <?= !empty($invite_code_required) ? 'checked' : '' ?> class="w-5 h-5 accent-emerald-500 shrink-0">
            </label>

            <label class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 cursor-pointer">
                <div>
                    <span class="block text-zinc-100">Allow users to generate invite codes</span>
                    <span class="block text-xs text-zinc-500 mt-0.5">Users can create and share invite codes from their settings.</span>
                </div>
                <input type="checkbox" name="invites_enabled" value="1" <?= !empty($invites_enabled) ? 'checked' : '' ?> class="w-5 h-5 accent-emerald-500 shrink-0">
            </label>

            <div>
                <label for="invite_codes_per_user" class="block text-sm text-zinc-400 mb-1">Invite codes per user</label>
                <input
                    type="number"
                    id="invite_codes_per_user"
                    name="invite_codes_per_user"
                    value="<?= (int)($invite_codes_per_user ?? 3) ?>"
                    min="0"
                    max="100"
                    class="w-32 bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100"
                >
                <p class="text-xs text-zinc-500 mt-1">Maximum invite codes each user can hold at once. Set to 0 for unlimited.</p>
            </div>

            <label class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 cursor-pointer">
                <div>
                    <span class="block text-zinc-100">Require email verification</span>
                    <span class="block text-xs text-zinc-500 mt-0.5">New accounts must verify their email address before accessing the app.</span>
                </div>
                <input type="checkbox" name="email_verification_required" value="1" <?= !empty($email_verification_required) ? 'checked' : '' ?> class="w-5 h-5 accent-emerald-500 shrink-0">
            </label>

            <div class="pt-2">
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-medium transition">Save account settings</button>
            </div>
        </form>
    </section>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-2xl">
        <h2 class="text-xl font-semibold mb-1">Brute Force Protection</h2>
        <p class="text-sm text-zinc-400 mb-5">Rate-limit status for failed authentication attempts.</p>

        <div class="space-y-3">
            <div class="rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 flex items-center justify-between gap-4">
                <span class="text-zinc-200">Failed login attempts (last 24 hours)</span>
                <span class="text-zinc-100 font-semibold"><?= (int)($failed_login_attempts_24h ?? 0) ?></span>
            </div>

            <div class="rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 flex items-center justify-between gap-4">
                <span class="text-zinc-200">Failed account creation attempts (last 24 hours)</span>
                <span class="text-zinc-100 font-semibold"><?= (int)($failed_registration_attempts_24h ?? 0) ?></span>
            </div>
        </div>

        <div class="mt-5 pt-5 border-t border-zinc-700">
            <h3 class="text-sm font-semibold text-zinc-200 mb-3">Currently banned IPs</h3>
            <?php $activeBannedIps = $active_banned_ips ?? []; ?>
            <?php if (!empty($activeBannedIps)): ?>
                <div class="space-y-2">
                    <?php foreach ($activeBannedIps as $bannedIp): ?>
                        <div class="rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 flex items-center justify-between gap-4">
                            <span class="text-zinc-200 font-mono"><?= htmlspecialchars((string)$bannedIp->ip_address, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-zinc-100 text-sm">
                                <?= (int)($bannedIp->minutes_left ?? 0) ?> minute<?= ((int)($bannedIp->minutes_left ?? 0) === 1) ? '' : 's' ?> left
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-zinc-500">No IPs are currently banned.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-2xl">
        <h2 class="text-xl font-semibold mb-1">More</h2>
        <p class="text-sm text-zinc-400 mb-5">Additional server-level settings.</p>

        <form method="POST" action="<?= htmlspecialchars(base_url('/config/more'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <label class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 cursor-pointer">
                <div>
                    <span class="block text-zinc-100">Show error details</span>
                    <span class="block text-xs text-zinc-500 mt-0.5">Display debug information (file, line, stack trace) on error pages. Disable in production.</span>
                </div>
                <input type="checkbox" name="error_display" value="1" <?= !empty($error_display) ? 'checked' : '' ?> class="w-5 h-5 accent-emerald-500 shrink-0">
            </label>

            <div class="pt-2">
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-medium transition">Save</button>
            </div>
        </form>
    </section>
</div>
