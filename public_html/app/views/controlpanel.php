<div class="p-8 overflow-auto space-y-8">
    <h1 class="text-3xl font-bold">Control Panel</h1>

    <?php
        $toastMessage = '';
        $toastKind = 'info';
        $currentAvatarUrl = User::avatarUrl($user);
        $autoOpenModalId = '';

        $flashSuccess = flash_get('success');
        $flashError = flash_get('error');
        $mailTestError = flash_get('mail_test_error');

        // Settings flash messages
        if ($flashSuccess === 'email_saved') {
            $toastMessage = 'Email updated successfully.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'email_change_sent') {
            $toastMessage = 'Verification code sent to your new email address.';
            $toastKind = 'success';
            $autoOpenModalId = 'cp-email-modal';
        } elseif ($flashSuccess === 'password_saved') {
            $toastMessage = 'Password updated successfully.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'username_saved') {
            $toastMessage = 'Username updated successfully.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'avatar_saved') {
            $toastMessage = 'Avatar updated successfully.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'avatar_removed') {
            $toastMessage = 'Avatar removed successfully.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'notifications_saved') {
            $toastMessage = 'Notification settings saved successfully.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'timezone_saved') {
            $toastMessage = 'Time zone saved successfully.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'invite_created') {
            $toastMessage = 'Invite code generated.';
            $toastKind = 'success';
            $autoOpenModalId = 'cp-invites-modal';
        } elseif ($flashSuccess === 'invite_deleted') {
            $toastMessage = 'Invite code deleted.';
            $toastKind = 'success';
            $autoOpenModalId = 'cp-invites-modal';
        } elseif ($flashSuccess === 'session_exited') {
            $toastMessage = 'Session ended successfully.';
            $toastKind = 'success';
        // Admin flash messages
        } elseif ($flashSuccess === 'mail_saved') {
            $toastMessage = 'Mail settings saved.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'mail_test_sent') {
            $toastMessage = 'Test email sent — check your inbox to confirm it arrived.';
            $toastKind = 'success';
        } elseif (str_starts_with((string)$flashSuccess, 'update_check_update_available:')) {
            $latestVersion = trim(substr((string)$flashSuccess, strlen('update_check_update_available:')));
            $toastMessage = $latestVersion !== ''
                ? ('Update available: ' . $latestVersion . '.')
                : 'An update is available.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'update_check_up_to_date') {
            $toastMessage = 'You are running the latest release.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'accounts_saved') {
            $toastMessage = 'Account settings saved.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'more_saved') {
            $toastMessage = 'Settings saved.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'attachments_saved') {
            $toastMessage = 'Attachment settings saved.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'announcement_saved') {
            $toastMessage = 'Announcement saved.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'storage_recalculated') {
            $toastMessage = 'Storage size recalculated.';
            $toastKind = 'success';
        // Error flash messages
        } elseif ($flashError === 'invite_limit') {
            $toastMessage = 'Invite limit reached. You cannot generate more codes right now.';
            $toastKind = 'error';
            $autoOpenModalId = 'cp-invites-modal';
        } elseif ($flashError === 'invite_disabled') {
            $toastMessage = 'Invite generation is currently disabled.';
            $toastKind = 'error';
        } elseif ($flashError === 'invite_delete_unavailable') {
            $toastMessage = 'This invite code cannot be deleted.';
            $toastKind = 'error';
            $autoOpenModalId = 'cp-invites-modal';
        } elseif ($flashError === 'email_invalid') {
            $toastMessage = 'Please enter a valid email address.';
            $toastKind = 'error';
            $autoOpenModalId = 'cp-email-modal';
        } elseif ($flashError === 'email_taken') {
            $toastMessage = 'That email address is already in use.';
            $toastKind = 'error';
            $autoOpenModalId = 'cp-email-modal';
        } elseif ($flashError === 'email_change_code_invalid') {
            $toastMessage = 'Verification code is invalid.';
            $toastKind = 'error';
            $autoOpenModalId = 'cp-email-modal';
        } elseif ($flashError === 'email_change_expired') {
            $toastMessage = 'Email change request expired. Please start again.';
            $toastKind = 'error';
            $autoOpenModalId = 'cp-email-modal';
        } elseif ($flashError === 'password_current_invalid') {
            $toastMessage = 'Current password is incorrect.';
            $toastKind = 'error';
            $autoOpenModalId = 'cp-password-modal';
        } elseif ($flashError === 'password_invalid') {
            $toastMessage = 'New password must be at least 8 characters.';
            $toastKind = 'error';
            $autoOpenModalId = 'cp-password-modal';
        } elseif ($flashError === 'password_mismatch') {
            $toastMessage = 'Password confirmation does not match.';
            $toastKind = 'error';
            $autoOpenModalId = 'cp-password-modal';
        } elseif ($flashError === 'username_invalid') {
            $toastMessage = 'Username must be 4-32 characters, start with a letter, and only contain lowercase letters and numbers.';
            $toastKind = 'error';
            $autoOpenModalId = 'cp-username-modal';
        } elseif ($flashError === 'username_same') {
            $toastMessage = 'New username must be different from your current username.';
            $toastKind = 'error';
            $autoOpenModalId = 'cp-username-modal';
        } elseif ($flashError === 'username_taken') {
            $toastMessage = 'That username is unavailable.';
            $toastKind = 'error';
            $autoOpenModalId = 'cp-username-modal';
        } elseif ($flashError === 'username_cooldown') {
            $toastMessage = 'Username was changed recently. Please try again after the cooldown period.';
            $toastKind = 'error';
            $autoOpenModalId = 'cp-username-modal';
        } elseif ($flashError === 'avatar_invalid_type') {
            $toastMessage = 'Avatar must be a JPG or PNG image.';
            $toastKind = 'error';
        } elseif ($flashError === 'avatar_too_large') {
            $toastMessage = 'Image is too large. Maximum size is 2048 x 2048 pixels.';
            $toastKind = 'error';
        } elseif ($flashError === 'avatar_upload_failed') {
            $toastMessage = 'Avatar upload failed. Please try again.';
            $toastKind = 'error';
        } elseif ($flashError === 'session_not_found') {
            $toastMessage = 'Session no longer exists or cannot be ended.';
            $toastKind = 'error';
        } elseif ($flashError === 'update_check_failed') {
            $toastMessage = 'Could not check for updates right now.';
            $toastKind = 'error';
        } elseif ($flashError === 'storage_recalculate_failed') {
            $toastMessage = 'Could not recalculate storage size right now.';
            $toastKind = 'error';
        }
    ?>

    <?php if ($toastMessage !== ''): ?>
        <div
            id="page-toast"
            data-toast-message="<?= htmlspecialchars($toastMessage, ENT_QUOTES, 'UTF-8') ?>"
            data-toast-kind="<?= htmlspecialchars($toastKind, ENT_QUOTES, 'UTF-8') ?>"
            class="hidden"
            aria-hidden="true"
        ></div>
    <?php endif; ?>

    <?php if (!empty($mailTestError)): ?>
        <div class="max-w-2xl rounded-xl border border-red-700 bg-red-950/60 px-5 py-4">
            <p class="text-sm font-semibold text-red-300 mb-1">Test email failed</p>
            <p class="text-sm text-red-200 break-words"><?= htmlspecialchars($mailTestError, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    <?php endif; ?>

    <?php if ($autoOpenModalId !== ''): ?>
        <div id="cp-modal-autoload" data-modal-id="<?= htmlspecialchars($autoOpenModalId, ENT_QUOTES, 'UTF-8') ?>" class="hidden" aria-hidden="true"></div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- ADMINISTRATION (admin only) -->
    <!-- ============================================================ -->
    <?php if (!empty($isAdmin)): ?>
    <div>
        <h2 class="text-xl font-semibold mb-4">Administration</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="<?= htmlspecialchars(base_url('/reports'), ENT_QUOTES, 'UTF-8') ?>" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1] relative">
                <i class="fa-regular fa-flag text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Reports</span>
                <?php if (((int)($pendingReportCount ?? 0)) > 0): ?>
                    <span class="absolute top-3 right-3 min-w-[1.25rem] h-5 px-1 rounded-full bg-red-600 text-white text-xs inline-flex items-center justify-center">
                        <?= htmlspecialchars((string)min(99, (int)($pendingReportCount ?? 0)) . (((int)($pendingReportCount ?? 0) > 99) ? '+' : ''), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="<?= htmlspecialchars(base_url('/users'), ENT_QUOTES, 'UTF-8') ?>" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-user-shield text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Users</span>
            </a>
            <a href="<?= htmlspecialchars(base_url('/tree'), ENT_QUOTES, 'UTF-8') ?>" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-diagram-project text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Tree</span>
            </a>
            <a href="<?= htmlspecialchars(base_url('/trash'), ENT_QUOTES, 'UTF-8') ?>" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-regular fa-trash-can text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Trash</span>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- SYSTEM (admin only) -->
    <!-- ============================================================ -->
    <?php if (!empty($isAdmin)): ?>
    <div>
        <h2 class="text-xl font-semibold mb-4">System</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <button type="button" data-modal-open="cp-mail-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-envelope text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Mail</span>
            </button>
            <button type="button" data-modal-open="cp-accounts-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-users-gear text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Accounts</span>
            </button>
            <button type="button" data-modal-open="cp-attachments-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-paperclip text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Attachments</span>
            </button>
            <button type="button" data-modal-open="cp-security-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-shield-halved text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Security</span>
            </button>
            <button type="button" data-modal-open="cp-errors-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-bug text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Errors & Logging</span>
            </button>
            <button type="button" data-modal-open="cp-updates-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-rotate text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Updates</span>
            </button>
            <button type="button" data-modal-open="cp-announcement-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-bullhorn text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Announcement</span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- ACCOUNT (all users) -->
    <!-- ============================================================ -->
    <div>
        <h2 class="text-xl font-semibold mb-4">Account</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <?php if (!empty($invitesEnabled)): ?>
            <button type="button" data-modal-open="cp-invites-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-ticket text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Invites</span>
            </button>
            <?php endif; ?>
            <button type="button" data-modal-open="cp-username-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-at text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Username</span>
            </button>
            <button type="button" data-modal-open="cp-email-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-envelope-open-text text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Email Address</span>
            </button>
            <button type="button" data-modal-open="cp-password-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-lock text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Change Password</span>
            </button>
            <button type="button" data-modal-open="cp-avatar-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-image text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Avatar</span>
            </button>
            <button type="button" data-modal-open="cp-timezone-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-clock text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Time Zone</span>
            </button>
            <button type="button" data-modal-open="cp-sessions-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-right-from-bracket text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Sessions</span>
            </button>
            <button type="button" data-modal-open="cp-2fa-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-mobile-screen text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">2FA</span>
            </button>
            <button type="button" data-modal-open="cp-notifications-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-bell text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Notifications</span>
            </button>
            <a href="<?= htmlspecialchars(base_url('/apikeys'), ENT_QUOTES, 'UTF-8') ?>" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-key text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">API Keys</span>
            </a>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- MORE (all users) -->
    <!-- ============================================================ -->
    <div>
        <h2 class="text-xl font-semibold mb-4">More</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <button type="button" data-modal-open="cp-browser-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-globe text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium">Browser Permissions</span>
            </button>
            <button type="button" data-modal-open="cp-licensing-modal" class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:bg-zinc-800 transition cursor-pointer aspect-[2/1]">
                <i class="fa-solid fa-scale-balanced text-2xl text-emerald-400"></i>
                <span class="text-sm text-zinc-200 font-medium text-center">Licensing & Attribution</span>
            </button>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODALS -->
<!-- ============================================================ -->

<?php
    // Shared modal wrapper classes
    $modalOverlayClass = 'fixed inset-0 z-50 hidden';
    $modalBackdropClass = 'absolute inset-0 bg-black/70';
    $modalCenterClass = 'relative z-10 flex min-h-full items-center justify-center p-4 lg:p-8';
    $modalBoxClass = 'w-full max-w-lg rounded-2xl border border-zinc-700 bg-zinc-900 p-6 max-h-[90vh] overflow-y-auto max-lg:!max-w-none max-lg:!rounded-none max-lg:!border-0 max-lg:min-h-[100dvh] max-lg:!max-h-none max-lg:flex max-lg:flex-col';
?>

<!-- ==================== ADMIN MODALS ==================== -->
<?php if (!empty($isAdmin)): ?>

<!-- Mail Modal -->
<div id="cp-mail-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-mail-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Mail</h3>
                <button type="button" data-modal-close="cp-mail-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <p class="text-sm text-zinc-400 mb-5">SMTP settings used to send verification and notification emails. Leave the password field blank to keep the current password.</p>
            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/mail'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label for="mail_host" class="block text-sm text-zinc-400 mb-1">Host</label>
                        <input type="text" id="mail_host" name="mail_host" value="<?= htmlspecialchars((string)($mail_host ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="smtp.example.com" autocomplete="off" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100">
                    </div>
                    <div>
                        <label for="mail_port" class="block text-sm text-zinc-400 mb-1">Port</label>
                        <input type="number" id="mail_port" name="mail_port" value="<?= htmlspecialchars((string)($mail_port ?? '587'), ENT_QUOTES, 'UTF-8') ?>" placeholder="587" min="1" max="65535" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100">
                    </div>
                </div>
                <div>
                    <label for="mail_user" class="block text-sm text-zinc-400 mb-1">Username</label>
                    <input type="text" id="mail_user" name="mail_user" value="<?= htmlspecialchars((string)($mail_user ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="user@example.com" autocomplete="off" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100">
                </div>
                <div>
                    <label for="mail_pass" class="block text-sm text-zinc-400 mb-1">Password</label>
                    <input type="password" id="mail_pass" name="mail_pass" value="" placeholder="Leave blank to keep current password" autocomplete="new-password" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100">
                </div>
                <div>
                    <label for="mail_from" class="block text-sm text-zinc-400 mb-1">From address</label>
                    <input type="text" id="mail_from" name="mail_from" value="<?= htmlspecialchars((string)($mail_from ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="noreply@example.com" autocomplete="off" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100">
                </div>
                <div>
                    <label for="mail_from_name" class="block text-sm text-zinc-400 mb-1">From name</label>
                    <input type="text" id="mail_from_name" name="mail_from_name" value="<?= htmlspecialchars((string)($mail_from_name ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="My App" autocomplete="off" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100">
                </div>
                <div class="pt-2 flex flex-wrap items-center gap-3">
                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-medium transition">Save mail settings</button>
                </div>
            </form>
            <div class="mt-5 pt-5 border-t border-zinc-700">
                <p class="text-sm text-zinc-400 mb-3">Send a test email to your account address to verify the settings above are working.</p>
                <form method="POST" action="<?= htmlspecialchars(base_url('/admin/mail/test'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-zinc-700 hover:bg-zinc-600 text-zinc-100 rounded-xl font-medium transition">
                        <i class="fa-regular fa-paper-plane"></i>
                        Send test email
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Accounts Modal -->
<div id="cp-accounts-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-accounts-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Accounts</h3>
                <button type="button" data-modal-close="cp-accounts-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <p class="text-sm text-zinc-400 mb-5">Control how new accounts are created and verified.</p>
            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/accounts'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
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
                    <input type="checkbox" name="invites_enabled" value="1" <?= !empty($admin_invites_enabled) ? 'checked' : '' ?> class="w-5 h-5 accent-emerald-500 shrink-0">
                </label>
                <div>
                    <label for="invite_codes_per_user" class="block text-sm text-zinc-400 mb-1">Invite codes per user</label>
                    <input type="number" id="invite_codes_per_user" name="invite_codes_per_user" value="<?= (int)($invite_codes_per_user ?? 3) ?>" min="0" max="100" class="w-32 bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100">
                    <p class="text-xs text-zinc-500 mt-1">Maximum invite codes each user can hold at once. Set to 0 for unlimited.</p>
                </div>
                <label class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 cursor-pointer">
                    <div>
                        <span class="block text-zinc-100">Require email verification</span>
                        <span class="block text-xs text-zinc-500 mt-0.5">New accounts must verify their email address before accessing the app.</span>
                    </div>
                    <input type="checkbox" name="email_verification_required" value="1" <?= !empty($email_verification_required) ? 'checked' : '' ?> class="w-5 h-5 accent-emerald-500 shrink-0">
                </label>
                <label class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 cursor-pointer">
                    <div>
                        <span class="block text-zinc-100">Email admin on new registration</span>
                        <span class="block text-xs text-zinc-500 mt-0.5">Send a notification email to all admin accounts when a new user registers. Requires mail to be configured.</span>
                    </div>
                    <input type="checkbox" name="new_user_notification" value="1" <?= !empty($new_user_notification) ? 'checked' : '' ?> class="w-5 h-5 accent-emerald-500 shrink-0">
                </label>
                <div class="pt-2">
                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-medium transition">Save account settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Attachments Modal -->
<div id="cp-attachments-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-attachments-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Attachments</h3>
                <button type="button" data-modal-close="cp-attachments-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <p class="text-sm text-zinc-400 mb-5">Control which file types users can attach and the maximum upload size.</p>
            <?php
                $rawTypes = strtolower((string)($attachments_accepted_file_types ?? 'png,jpg'));
                $enabledTypes = array_flip(array_map('trim', explode(',', $rawTypes)));
                $typeCheck = function(string $ext) use ($enabledTypes): string {
                    return isset($enabledTypes[$ext]) ? 'checked' : '';
                };
            ?>
            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/attachments'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-5" id="cp-attachments-type-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <div class="flex items-center justify-end">
                    <button type="button" id="cp-attachments-select-all-btn" class="text-xs text-zinc-400 hover:text-zinc-200 underline underline-offset-2">Select all</button>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-zinc-500 mb-2">Images</p>
                    <div class="flex flex-wrap gap-3">
                        <?php foreach ([['png', 'PNG'], ['jpg', 'JPG'], ['webp', 'WebP']] as [$ext, $label]): ?>
                        <label class="flex items-center gap-2.5 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 cursor-pointer">
                            <input type="checkbox" name="type_<?= $ext ?>" value="1" <?= $typeCheck($ext) ?> class="w-4 h-4 accent-emerald-500">
                            <span class="text-zinc-100 text-sm"><?= $label ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-zinc-500 mb-2">Video</p>
                    <div class="flex flex-wrap gap-3">
                        <?php foreach ([['mp4', 'MP4'], ['webm', 'WebM']] as [$ext, $label]): ?>
                        <label class="flex items-center gap-2.5 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 cursor-pointer">
                            <input type="checkbox" name="type_<?= $ext ?>" value="1" <?= $typeCheck($ext) ?> class="w-4 h-4 accent-emerald-500">
                            <span class="text-zinc-100 text-sm"><?= $label ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-zinc-500 mb-2">Files</p>
                    <div class="flex flex-wrap gap-3">
                        <?php foreach ([['pdf', 'PDF'], ['odt', 'ODT'], ['doc', 'DOC'], ['docx', 'DOCX'], ['zip', 'ZIP'], ['7z', '7Z']] as [$ext, $label]): ?>
                        <label class="flex items-center gap-2.5 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 cursor-pointer">
                            <input type="checkbox" name="type_<?= $ext ?>" value="1" <?= $typeCheck($ext) ?> class="w-4 h-4 accent-emerald-500">
                            <span class="text-zinc-100 text-sm"><?= $label ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label for="cp_attachments_maximum_file_size_mb" class="block text-sm text-zinc-400 mb-1">Maximum file size (MB)</label>
                    <input type="number" id="cp_attachments_maximum_file_size_mb" name="attachments_maximum_file_size_mb" value="<?= (int)($attachments_maximum_file_size_mb ?? 10) ?>" min="1" max="512" class="w-32 bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100">
                    <p class="text-xs text-zinc-500 mt-1">Applies per file. The effective limit may be lower if your PHP <code class="text-zinc-400">upload_max_filesize</code> or <code class="text-zinc-400">post_max_size</code> is smaller.</p>
                </div>
                <div class="pt-2">
                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-medium transition">Save attachment settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Security Modal -->
<div id="cp-security-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-security-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Security</h3>
                <button type="button" data-modal-close="cp-security-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <p class="text-sm text-zinc-400 mb-5">Rate-limit status for failed authentication attempts.</p>
            <div class="space-y-3">
                <div class="rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 flex items-center justify-between gap-4">
                    <span class="text-zinc-200">Failed login attempts (last 24h)</span>
                    <span class="text-zinc-100 font-semibold"><?= (int)($failed_login_attempts_24h ?? 0) ?></span>
                </div>
                <div class="rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 flex items-center justify-between gap-4">
                    <span class="text-zinc-200">Failed account creation attempts (last 24h)</span>
                    <span class="text-zinc-100 font-semibold"><?= (int)($failed_registration_attempts_24h ?? 0) ?></span>
                </div>
            </div>
            <div class="mt-5 pt-5 border-t border-zinc-700">
                <h4 class="text-sm font-semibold text-zinc-200 mb-3">Currently banned IPs</h4>
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
        </div>
    </div>
</div>

<!-- Errors & Logging Modal -->
<div id="cp-errors-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-errors-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Errors & Logging</h3>
                <button type="button" data-modal-close="cp-errors-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/more'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <?php if (!empty($check_for_updates)): ?><input type="hidden" name="check_for_updates" value="1"><?php endif; ?>
                <label class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 cursor-pointer">
                    <div>
                        <span class="block text-zinc-100">Show error details</span>
                        <span class="block text-xs text-zinc-500 mt-0.5">Display debug information (file, line, stack trace) on error pages. Disable in production.</span>
                    </div>
                    <input type="checkbox" name="error_display" value="1" <?= !empty($error_display) ? 'checked' : '' ?> class="w-5 h-5 accent-emerald-500 shrink-0">
                </label>
                <label class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 cursor-pointer">
                    <div>
                        <span class="block text-zinc-100">Detailed attachment logging</span>
                        <span class="block text-xs text-zinc-500 mt-0.5">Log every attachment upload attempt (success or failure) to attachment.log in the log directory.</span>
                    </div>
                    <input type="checkbox" name="attachment_logging" value="1" <?= !empty($attachment_logging) ? 'checked' : '' ?> class="w-5 h-5 accent-emerald-500 shrink-0">
                </label>
                <div class="pt-2">
                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-medium transition">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Updates Modal -->
<div id="cp-updates-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-updates-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Updates</h3>
                <button type="button" data-modal-close="cp-updates-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/more'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <?php if (!empty($error_display)): ?><input type="hidden" name="error_display" value="1"><?php endif; ?>
                <?php if (!empty($attachment_logging)): ?><input type="hidden" name="attachment_logging" value="1"><?php endif; ?>
                <label class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 cursor-pointer">
                    <div>
                        <span class="block text-zinc-100">Check for updates on admin login</span>
                        <span class="block text-xs text-zinc-500 mt-0.5">When enabled, admin login checks GitHub releases and shows a notification if a newer version exists.</span>
                    </div>
                    <input type="checkbox" name="check_for_updates" value="1" <?= !empty($check_for_updates) ? 'checked' : '' ?> class="w-5 h-5 accent-emerald-500 shrink-0">
                </label>
                <div class="pt-1">
                    <p class="text-xs text-zinc-500 mb-2">Run the same update check immediately.</p>
                    <button type="submit" formaction="<?= htmlspecialchars(base_url('/admin/check-updates'), ENT_QUOTES, 'UTF-8') ?>" class="w-full flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 text-left text-zinc-100 hover:bg-zinc-800/50 transition">
                        <span class="block">Check for updates now</span>
                        <span class="text-xs text-zinc-400">Run</span>
                    </button>
                </div>
                <div class="pt-2">
                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-medium transition">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Announcement Modal -->
<div id="cp-announcement-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-announcement-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Announcement</h3>
                <button type="button" data-modal-close="cp-announcement-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <p class="text-sm text-zinc-400 mb-5">Show a plain text alert on the dashboard for all users. Leave blank to hide it.</p>
            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/announcement'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <div>
                    <div class="mb-1 flex items-center justify-between gap-3">
                        <label for="cp_announcement_message" class="block text-sm text-zinc-400">Message (max 1000 characters)</label>
                        <span id="cp_announcement_counter" class="text-xs text-zinc-500">0/1000</span>
                    </div>
                    <textarea id="cp_announcement_message" name="announcement_message" maxlength="1000" rows="3" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100" placeholder="Scheduled maintenance tonight at 10:00 PM UTC."><?= htmlspecialchars((string)($announcement_message ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="pt-2">
                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-medium transition">Save announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ==================== ACCOUNT MODALS ==================== -->

<!-- Invites Modal -->
<?php if (!empty($invitesEnabled)): ?>
<div id="cp-invites-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-invites-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Invites</h3>
                <button type="button" data-modal-close="cp-invites-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <p class="text-sm text-zinc-400">Generated: <?= (int)$inviteCount ?> / <?= (int)$inviteLimit ?></p>
                <form method="POST" action="<?= htmlspecialchars(base_url('/settings/invites/generate'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 px-4 py-2 rounded-lg disabled:bg-zinc-700 disabled:text-zinc-400 disabled:cursor-not-allowed" <?= $inviteCount >= $inviteLimit ? 'disabled' : '' ?>>
                        Generate invite code
                    </button>
                </form>
            </div>
            <div class="space-y-2 text-sm">
                <?php if (empty($invites)): ?>
                    <p class="text-zinc-400">No invite codes available.</p>
                <?php else: ?>
                    <?php foreach ($invites as $invite): ?>
                        <div class="flex items-center justify-between gap-3 bg-zinc-800 rounded-lg px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span class="font-mono"><?= htmlspecialchars($invite->code, ENT_QUOTES, 'UTF-8') ?></span>
                                <button type="button" class="text-zinc-400 hover:text-zinc-200" title="Copy to clipboard" data-copy-invite data-copy-value="<?= htmlspecialchars($invite->code, ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                                <?php if (!$invite->used_by): ?>
                                    <form method="POST" action="<?= htmlspecialchars(base_url('/settings/invites/delete'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Delete this invite code?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="invite_code" value="<?= htmlspecialchars($invite->code, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="text-zinc-500 hover:text-red-300" title="Delete invite code"><i class="fa-regular fa-trash-can"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <span class="block <?= $invite->used_by ? 'text-zinc-400' : 'text-emerald-400' ?>"><?= $invite->used_by ? 'Used' : 'Available' ?></span>
                                <?php if (!empty($invite->used_by_username)): ?>
                                    <span class="block text-xs text-zinc-500">by <?= htmlspecialchars($invite->used_by_username, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Username Modal -->
<div id="cp-username-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-username-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Change Username</h3>
                <button type="button" data-modal-close="cp-username-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <div class="mb-4">
                <p class="text-sm text-zinc-400 mb-1">Current username</p>
                <p class="text-zinc-100"><?= htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <?php if (!$usernameCanChangeNow && !empty($usernameChangeAvailableAt)): ?>
                <p class="text-sm text-amber-300 mb-4">Username recently changed. You can change it again on <?= htmlspecialchars($usernameChangeAvailableAt, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <form method="POST" action="<?= htmlspecialchars(base_url('/settings/profile/username'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <label class="block text-sm text-zinc-300">New username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') ?>" minlength="4" maxlength="32" pattern="[a-z][a-z0-9]{3,31}" autocapitalize="none" spellcheck="false" data-modal-autofocus required <?= $usernameCanChangeNow ? '' : 'disabled' ?> class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50">
                <div class="mt-4 flex gap-2">
                    <button type="submit" <?= $usernameCanChangeNow ? '' : 'disabled' ?> class="bg-emerald-600 hover:bg-emerald-500 px-6 py-2 rounded-xl disabled:bg-zinc-700 disabled:text-zinc-400 disabled:cursor-not-allowed">Save username</button>
                    <button type="button" data-modal-close="cp-username-modal" class="bg-zinc-700 hover:bg-zinc-600 px-6 py-2 rounded-xl">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Email Modal -->
<div id="cp-email-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-email-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Change Email</h3>
                <button type="button" data-modal-close="cp-email-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <?php
                $pendingEmailValue = !empty($pendingEmailChange) ? (string)$pendingEmailChange->new_email : (string)$user->email;
                $pendingEmailSeconds = !empty($pendingEmailChange) ? max(0, (int)$pendingEmailChange->seconds_remaining) : 0;
                $pendingEmailMinutes = (int)ceil($pendingEmailSeconds / 60);
                if ($pendingEmailMinutes < 1) { $pendingEmailMinutes = 1; }
            ?>
            <?php if (!empty($pendingEmailChange)): ?>
                <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-200 mb-4">
                    Email change pending for <span class="font-semibold"><?= htmlspecialchars($pendingEmailChange->new_email, ENT_QUOTES, 'UTF-8') ?></span>.
                    Enter the verification code within <?= (int)$pendingEmailMinutes ?> minute<?= $pendingEmailMinutes === 1 ? '' : 's' ?>.
                </div>
            <?php endif; ?>
            <form method="POST" action="<?= htmlspecialchars(base_url('/settings/account/email'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <label class="block text-sm text-zinc-300">New email address</label>
                <input type="email" name="email" id="cp-change-email-input" data-current-email="<?= htmlspecialchars((string)$user->email, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($pendingEmailValue, ENT_QUOTES, 'UTF-8') ?>" <?= empty($pendingEmailChange) ? 'data-modal-autofocus' : '' ?> inputmode="email" autocomplete="email" required class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <p class="text-xs text-zinc-400">We send a 6-digit verification code to the new address. Code expires in 10 minutes.</p>
                <div class="mt-4 flex gap-2">
                    <button type="submit" id="cp-change-email-send-button" class="bg-emerald-600 hover:bg-emerald-500 px-6 py-2 rounded-xl">Send verification code</button>
                    <button type="button" data-modal-close="cp-email-modal" class="bg-zinc-700 hover:bg-zinc-600 px-6 py-2 rounded-xl">Close</button>
                </div>
            </form>
            <?php if (!empty($pendingEmailChange)): ?>
                <div class="my-5 h-px bg-zinc-700"></div>
                <form method="POST" action="<?= htmlspecialchars(base_url('/settings/account/email/verify'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <p class="text-sm text-zinc-300">Enter the code sent to <span class="font-semibold text-zinc-100"><?= htmlspecialchars($pendingEmailChange->new_email, ENT_QUOTES, 'UTF-8') ?></span>.</p>
                    <label class="block text-sm text-zinc-300">Verification code</label>
                    <input type="text" name="code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" <?= !empty($pendingEmailChange) ? 'data-modal-autofocus' : '' ?> required class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-zinc-100 tracking-widest focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <div class="mt-4 flex gap-2">
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 px-6 py-2 rounded-xl">Verify email</button>
                        <button type="button" data-modal-close="cp-email-modal" class="bg-zinc-700 hover:bg-zinc-600 px-6 py-2 rounded-xl">Cancel</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Password Modal -->
<div id="cp-password-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-password-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Change Password</h3>
                <button type="button" data-modal-close="cp-password-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <form method="POST" action="<?= htmlspecialchars(base_url('/settings/account/password'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <label class="block text-sm text-zinc-300">Current password</label>
                <input type="password" name="current_password" data-modal-autofocus required class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <label class="block text-sm text-zinc-300">New password</label>
                <input type="password" name="new_password" required minlength="8" class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <label class="block text-sm text-zinc-300">Confirm new password</label>
                <input type="password" name="confirm_password" required minlength="8" class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <div class="mt-4 flex gap-2">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 px-6 py-2 rounded-xl">Save password</button>
                    <button type="button" data-modal-close="cp-password-modal" class="bg-zinc-700 hover:bg-zinc-600 px-6 py-2 rounded-xl">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Avatar Modal -->
<div id="cp-avatar-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-avatar-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Avatar</h3>
                <button type="button" data-modal-close="cp-avatar-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <p class="text-sm text-zinc-400 mb-4">JPG or PNG. Images are resized to 256 &times; 256. Maximum 2048 &times; 2048 pixels.</p>
            <div class="flex items-center gap-5">
                <div id="avatar-preview-wrap" class="relative shrink-0" data-initial="<?= htmlspecialchars(User::avatarInitial($user->username), ENT_QUOTES, 'UTF-8') ?>" data-initials-class="w-16 h-16 rounded-full border border-zinc-700 flex items-center justify-center text-xl font-semibold <?= htmlspecialchars(User::avatarColorClasses($user->user_number), ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($currentAvatarUrl): ?>
                        <img id="avatar-preview-img" src="<?= htmlspecialchars($currentAvatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Your avatar" class="w-16 h-16 rounded-full object-cover border border-zinc-700">
                    <?php else: ?>
                        <div id="avatar-preview-initials" class="w-16 h-16 rounded-full border border-zinc-700 flex items-center justify-center text-xl font-semibold <?= htmlspecialchars(User::avatarColorClasses($user->user_number), ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(User::avatarInitial($user->username), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                    <div id="avatar-upload-spinner" class="hidden absolute inset-0 rounded-full bg-black/60 flex items-center justify-center">
                        <i class="fa-solid fa-spinner fa-spin text-white text-lg"></i>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <label id="avatar-upload-label" class="cursor-pointer bg-zinc-700 hover:bg-zinc-600 px-4 py-2 rounded-lg text-sm select-none">
                        <span id="avatar-upload-label-text"><?= $currentAvatarUrl ? 'Change avatar' : 'Upload avatar' ?></span>
                        <input id="avatar-file-input" type="file" accept="image/jpeg,image/png" class="hidden">
                    </label>
                    <button id="avatar-delete-btn" type="button" class="<?= $currentAvatarUrl ? '' : 'hidden ' ?>px-4 py-2 rounded-lg text-sm bg-zinc-800 border border-zinc-600 hover:border-red-500 hover:text-red-400 text-zinc-300">
                        Delete avatar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Avatar Confirmation Modal -->
<div id="cp-delete-avatar-modal" class="fixed inset-0 z-[60] hidden" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/70" data-modal-close="cp-delete-avatar-modal"></div>
    <div class="relative z-10 flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-sm rounded-2xl border border-zinc-700 bg-zinc-900 p-6" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Delete avatar?</h3>
                <button type="button" data-modal-close="cp-delete-avatar-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <p class="text-sm text-zinc-300 mb-6">Your avatar will be removed and replaced with your initials.</p>
            <div class="flex gap-3">
                <button id="avatar-delete-confirm-btn" type="button" class="bg-red-600 hover:bg-red-500 px-6 py-2 rounded-xl text-sm">Delete</button>
                <button type="button" data-modal-close="cp-delete-avatar-modal" class="bg-zinc-700 hover:bg-zinc-600 px-6 py-2 rounded-xl text-sm">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Timezone Modal -->
<div id="cp-timezone-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-timezone-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Time Zone</h3>
                <button type="button" data-modal-close="cp-timezone-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <form method="POST" action="<?= htmlspecialchars(base_url('/settings/timezone'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <p class="text-sm text-zinc-300 mb-3">All times are stored in UTC. Select your local time zone to display them correctly.</p>
                <select name="timezone" class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <?php
                        $timezoneOptions = [
                            'UTC-12:00','UTC-11:00','UTC-10:00','UTC-9:30','UTC-9:00',
                            'UTC-8:00','UTC-7:00','UTC-6:00','UTC-5:00','UTC-4:30',
                            'UTC-4:00','UTC-3:30','UTC-3:00','UTC-2:00','UTC-1:00',
                            'UTC+0','UTC+1:00','UTC+2:00','UTC+3:00','UTC+3:30',
                            'UTC+4:00','UTC+4:30','UTC+5:00','UTC+5:30','UTC+5:45',
                            'UTC+6:00','UTC+6:30','UTC+7:00','UTC+8:00','UTC+8:30',
                            'UTC+8:45','UTC+9:00','UTC+9:30','UTC+10:00','UTC+10:30',
                            'UTC+11:00','UTC+12:00','UTC+12:45','UTC+13:00','UTC+14:00'
                        ];
                        foreach ($timezoneOptions as $tz):
                    ?>
                        <option value="<?= htmlspecialchars($tz, ENT_QUOTES, 'UTF-8') ?>" <?= ($userTimezone ?? 'UTC+0') === $tz ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tz, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="mt-4 flex gap-2">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 px-6 py-2 rounded-xl">Save time zone</button>
                    <button type="button" data-modal-close="cp-timezone-modal" class="bg-zinc-700 hover:bg-zinc-600 px-6 py-2 rounded-xl">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Sessions Modal -->
<div id="cp-sessions-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-sessions-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Sessions</h3>
                <button type="button" data-modal-close="cp-sessions-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <div class="space-y-2 text-sm">
                <?php if (empty($sessions)): ?>
                    <p class="text-zinc-400">No active sessions.</p>
                <?php else: ?>
                    <?php foreach ($sessions as $session): ?>
                        <?php
                            $loggedInAt = strtotime((string)$session->logged_in_at . ' UTC');
                            if ($loggedInAt && preg_match('/^UTC([+-])(\d{1,2})(?::(\d{2}))?$/', $userTimezone ?? 'UTC+0', $_tzm)) {
                                $loggedInAt += ($_tzm[1] === '+' ? 1 : -1) * ((int)$_tzm[2] * 3600 + (int)($_tzm[3] ?? 0) * 60);
                            }
                            $loggedInLabel = $loggedInAt ? date('M j, Y H:i', $loggedInAt) : (string)$session->logged_in_at;
                        ?>
                        <div class="bg-zinc-800 rounded-lg px-3 py-3 flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <p class="text-zinc-100">
                                    <span class="text-zinc-400">Logged in:</span>
                                    <?= htmlspecialchars((string)$loggedInLabel, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (!empty($session->is_current)): ?>
                                        <span class="ml-2 text-emerald-400 text-xs">(Current session)</span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-zinc-300"><span class="text-zinc-400">IP:</span> <?= htmlspecialchars((string)$session->ip_address, ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-zinc-300"><span class="text-zinc-400">Browser:</span> <?= htmlspecialchars((string)$session->browser, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <form method="POST" action="<?= htmlspecialchars(base_url('/settings/sessions/exit'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('End this session?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="session_id" value="<?= (int)$session->id ?>">
                                <button type="submit" class="text-red-300 hover:text-red-200 underline decoration-red-400 whitespace-nowrap">Exit session</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 2FA Modal -->
<div id="cp-2fa-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-2fa-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Two-Factor Authentication</h3>
                <button type="button" data-modal-close="cp-2fa-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <p class="text-zinc-400">Coming soon.</p>
        </div>
    </div>
</div>

<!-- Notifications Modal -->
<div id="cp-notifications-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-notifications-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Notifications</h3>
                <button type="button" data-modal-close="cp-notifications-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <p class="text-sm text-zinc-400 mb-5">Changes save automatically.</p>
            <div class="space-y-4" id="notification-settings-toggles">
                <label class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                    <span class="text-zinc-100">Browser notifications</span>
                    <input type="checkbox" data-notification-setting="browser_notifications" <?= $browserNotif ? 'checked' : '' ?> class="w-6 h-6 accent-emerald-500">
                </label>
                <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                    <span class="text-zinc-100">Sound: New friend request</span>
                    <div class="flex items-center gap-3">
                        <button type="button" data-notification-sound-preview="friend_request" class="px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Preview</button>
                        <input type="checkbox" data-notification-setting="sound_friend_request" <?= $friendRequestSoundNotif ? 'checked' : '' ?> class="w-6 h-6 accent-emerald-500">
                    </div>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                    <span class="text-zinc-100">Sound: New chat message</span>
                    <div class="flex items-center gap-3">
                        <button type="button" data-notification-sound-preview="new_message" class="px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Preview</button>
                        <input type="checkbox" data-notification-setting="sound_new_message" <?= $newMessageSoundNotif ? 'checked' : '' ?> class="w-6 h-6 accent-emerald-500">
                    </div>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                    <span class="text-zinc-100">Sound: Other notifications</span>
                    <div class="flex items-center gap-3">
                        <button type="button" data-notification-sound-preview="other" class="px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Preview</button>
                        <input type="checkbox" data-notification-setting="sound_other_notifications" <?= $otherNotificationSoundNotif ? 'checked' : '' ?> class="w-6 h-6 accent-emerald-500">
                    </div>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                    <span class="text-zinc-100">Sound: Outgoing call ringing</span>
                    <div class="flex items-center gap-3">
                        <button type="button" data-notification-sound-preview="call" class="px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Preview</button>
                        <input type="checkbox" data-notification-setting="sound_outgoing_call_ring" <?= $outgoingCallRingSoundNotif ? 'checked' : '' ?> class="w-6 h-6 accent-emerald-500">
                    </div>
                </div>
            </div>
            <p id="notification-settings-status" class="mt-3 text-xs text-zinc-500" aria-live="polite"></p>
        </div>
    </div>
</div>

<!-- ==================== MORE MODALS ==================== -->

<!-- Browser Permissions Modal -->
<div id="cp-browser-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-browser-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Browser Permissions</h3>
                <button type="button" data-modal-close="cp-browser-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <p class="text-sm text-zinc-400 mb-5">This applies to your current browser/device only.</p>
            <div class="space-y-3" id="browser-permissions-list">
                <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                    <span class="text-zinc-100">Sound</span>
                    <div class="flex items-center gap-2">
                        <button type="button" data-browser-permission-test="sound" class="hidden px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Test</button>
                        <span data-browser-permission-status="sound" class="text-xs px-2.5 py-1 rounded-full border border-zinc-600 bg-zinc-700/50 text-zinc-200">Checking&hellip;</span>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                    <span class="text-zinc-100">Webcam</span>
                    <div class="flex items-center gap-2">
                        <button type="button" data-browser-permission-test="camera" class="hidden px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Test</button>
                        <span data-browser-permission-status="camera" class="text-xs px-2.5 py-1 rounded-full border border-zinc-600 bg-zinc-700/50 text-zinc-200">Checking&hellip;</span>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                    <span class="text-zinc-100">Microphone</span>
                    <div class="flex items-center gap-2">
                        <button type="button" data-browser-permission-test="microphone" class="hidden px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Test</button>
                        <span data-browser-permission-status="microphone" class="text-xs px-2.5 py-1 rounded-full border border-zinc-600 bg-zinc-700/50 text-zinc-200">Checking&hellip;</span>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                    <span class="text-zinc-100">Screensharing</span>
                    <div class="flex items-center gap-2">
                        <button type="button" data-browser-permission-test="screenshare" class="hidden px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Test</button>
                        <span data-browser-permission-status="screenshare" class="text-xs px-2.5 py-1 rounded-full border border-zinc-600 bg-zinc-700/50 text-zinc-200">Checking&hellip;</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Licensing & Attribution Modal -->
<div id="cp-licensing-modal" class="<?= $modalOverlayClass ?>" role="dialog" aria-modal="true">
    <div class="<?= $modalBackdropClass ?>" data-modal-close="cp-licensing-modal"></div>
    <div class="<?= $modalCenterClass ?>">
        <div class="<?= $modalBoxClass ?>" data-modal-box>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-xl font-semibold">Licensing & Attribution</h3>
                <button type="button" data-modal-close="cp-licensing-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">&times;</button>
            </div>
            <div class="space-y-3 text-zinc-200">
                <div class="border border-zinc-700 bg-zinc-800/40 rounded-xl px-4 py-3">
                    <span class="text-zinc-400">Open source license</span>
                    <div class="font-semibold mt-1">GPL-3.0</div>
                </div>
                <div class="border border-zinc-700 bg-zinc-800/40 rounded-xl px-4 py-3">
                    <span class="text-zinc-400">Front end framework</span>
                    <div class="font-semibold mt-1">Tailwind</div>
                </div>
                <div class="border border-zinc-700 bg-zinc-800/40 rounded-xl px-4 py-3">
                    <span class="text-zinc-400">Icon font</span>
                    <div class="font-semibold mt-1">Font Awesome Free</div>
                </div>
                <div class="border border-zinc-700 bg-zinc-800/40 rounded-xl px-4 py-3">
                    <span class="text-zinc-400">Emojis</span>
                    <div class="font-semibold mt-1">OpenMoji</div>
                </div>
            </div>
            <div class="mt-5 flex items-center gap-4">
                <a href="https://prologue.chat" target="_blank" class="inline-flex items-center gap-1.5 text-sm text-zinc-400 hover:text-zinc-100 transition-colors">Prologue.chat <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i></a>
                <a href="https://github.com/michaelstaake/Prologue/issues" target="_blank" class="inline-flex items-center gap-1.5 text-sm text-zinc-400 hover:text-zinc-100 transition-colors">Report Problems <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i></a>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================================ -->
<script>
(function () {
    // ---- Generic modal open/close ----
    var openButtons = document.querySelectorAll('[data-modal-open]');
    var closeButtons = document.querySelectorAll('[data-modal-close]');

    function openModal(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');

        if (modalId === 'cp-email-modal') {
            updateEmailSendButtonState();
        }

        var autofocusInput = modal.querySelector('[data-modal-autofocus]');
        if (autofocusInput && typeof autofocusInput.focus === 'function') {
            setTimeout(function () {
                autofocusInput.focus();
                if (typeof autofocusInput.select === 'function') {
                    autofocusInput.select();
                }
            }, 0);
        }
    }

    function closeModal(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.add('hidden');

        if (!Array.from(document.querySelectorAll('[role="dialog"]')).some(function (el) {
            return !el.classList.contains('hidden');
        })) {
            document.body.classList.remove('overflow-hidden');
        }
    }

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var modalId = button.getAttribute('data-modal-open');
            if (modalId) openModal(modalId);
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var modalId = button.getAttribute('data-modal-close');
            if (modalId) closeModal(modalId);
        });
    });

    // ---- Close modal when clicking outside the modal box ----
    document.querySelectorAll('[role="dialog"]').forEach(function (overlay) {
        overlay.addEventListener('mousedown', function (event) {
            // Only close if the click target is not inside the modal box.
            // The modal box is the deepest nested wrapper (overlay > backdrop, overlay > center > box).
            if (!event.target.closest('[data-modal-box]')) {
                closeModal(overlay.id);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('[role="dialog"]').forEach(function (modal) {
            if (!modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
            }
        });
        document.body.classList.remove('overflow-hidden');
    });

    // ---- Auto-open modal from flash ----
    var autoloadModal = document.getElementById('cp-modal-autoload');
    if (autoloadModal) {
        var modalId = autoloadModal.getAttribute('data-modal-id');
        if (modalId) openModal(modalId);
    }

    // ---- Email validation ----
    var changeEmailInput = document.getElementById('cp-change-email-input');
    var changeEmailSendButton = document.getElementById('cp-change-email-send-button');

    function updateEmailSendButtonState() {
        if (!changeEmailInput || !changeEmailSendButton) return;
        var enteredEmail = String(changeEmailInput.value || '').trim().toLowerCase();
        var currentEmail = String(changeEmailInput.getAttribute('data-current-email') || '').trim().toLowerCase();
        var isDifferentEmail = enteredEmail !== '' && enteredEmail !== currentEmail;
        var isValidEmail = typeof changeEmailInput.checkValidity === 'function'
            ? changeEmailInput.checkValidity()
            : /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(enteredEmail);
        var canSend = isDifferentEmail && isValidEmail;
        changeEmailSendButton.disabled = !canSend;
        changeEmailSendButton.classList.toggle('hidden', !canSend);
    }

    if (changeEmailInput) {
        changeEmailInput.addEventListener('input', updateEmailSendButtonState);
        changeEmailInput.addEventListener('change', updateEmailSendButtonState);

        var changeEmailForm = changeEmailInput.closest('form');
        if (changeEmailForm) {
            changeEmailForm.addEventListener('submit', function (event) {
                updateEmailSendButtonState();
                if (typeof changeEmailInput.checkValidity === 'function' && !changeEmailInput.checkValidity()) {
                    event.preventDefault();
                    if (typeof changeEmailInput.reportValidity === 'function') {
                        changeEmailInput.reportValidity();
                    }
                    return;
                }
                if (changeEmailSendButton && changeEmailSendButton.disabled) {
                    event.preventDefault();
                }
            });
        }

        updateEmailSendButtonState();
    }

    // ---- Avatar upload/delete ----
    var avatarFileInput = document.getElementById('avatar-file-input');
    var avatarPreviewWrap = document.getElementById('avatar-preview-wrap');
    var avatarUploadSpinner = document.getElementById('avatar-upload-spinner');
    var avatarUploadLabel = document.getElementById('avatar-upload-label');
    var avatarUploadLabelText = document.getElementById('avatar-upload-label-text');
    var avatarDeleteBtn = document.getElementById('avatar-delete-btn');
    var avatarDeleteConfirmBtn = document.getElementById('avatar-delete-confirm-btn');
    var deleteAvatarModal = document.getElementById('cp-delete-avatar-modal');

    function getAvatarInitials() {
        return document.getElementById('avatar-preview-initials') || null;
    }

    function setAvatarImg(url) {
        var img = document.getElementById('avatar-preview-img');
        var initials = getAvatarInitials();
        if (initials) initials.remove();
        if (!img) {
            img = document.createElement('img');
            img.id = 'avatar-preview-img';
            img.alt = 'Your avatar';
            img.className = 'w-16 h-16 rounded-full object-cover border border-zinc-700';
            avatarPreviewWrap.insertBefore(img, avatarUploadSpinner);
        }
        img.src = url;
        avatarUploadLabelText.textContent = 'Change avatar';
        if (avatarDeleteBtn) avatarDeleteBtn.classList.remove('hidden');
    }

    function clearAvatarImg() {
        var img = document.getElementById('avatar-preview-img');
        if (img) img.remove();
        if (!getAvatarInitials()) {
            var div = document.createElement('div');
            div.id = 'avatar-preview-initials';
            div.className = avatarPreviewWrap.dataset.initialsClass || 'w-16 h-16 rounded-full border border-zinc-700 flex items-center justify-center text-xl font-semibold bg-emerald-700 text-emerald-100';
            div.textContent = avatarPreviewWrap.dataset.initial || '?';
            avatarPreviewWrap.insertBefore(div, avatarUploadSpinner);
        }
        avatarUploadLabelText.textContent = 'Upload avatar';
        if (avatarDeleteBtn) avatarDeleteBtn.classList.add('hidden');
    }

    if (avatarFileInput) {
        avatarFileInput.addEventListener('change', function () {
            var file = avatarFileInput.files && avatarFileInput.files[0];
            if (!file) return;

            var allowed = ['image/jpeg', 'image/png'];
            if (!allowed.includes(file.type)) {
                showToast('Avatar must be a JPG or PNG image.', 'error');
                avatarFileInput.value = '';
                return;
            }

            var formData = new FormData();
            formData.append('csrf_token', window.CSRF_TOKEN || (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute?.('content') || '');
            formData.append('avatar', file);

            if (avatarUploadSpinner) avatarUploadSpinner.classList.remove('hidden');
            if (avatarUploadLabel) avatarUploadLabel.style.pointerEvents = 'none';

            fetch(<?= json_encode(base_url('/settings/avatar')) ?>, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success && data.avatar_url) {
                    setAvatarImg(data.avatar_url);
                    showToast('Avatar updated.', 'success');
                } else {
                    var errorMap = {
                        'avatar_invalid_type': 'Avatar must be a JPG or PNG image.',
                        'avatar_too_large': 'Image is too large. Maximum size is 2048 \u00d7 2048 pixels.',
                        'avatar_upload_failed': 'Avatar upload failed. Please try again.'
                    };
                    showToast(errorMap[data && data.error] || 'Avatar upload failed. Please try again.', 'error');
                }
            })
            .catch(function () {
                showToast('Avatar upload failed. Please try again.', 'error');
            })
            .finally(function () {
                if (avatarUploadSpinner) avatarUploadSpinner.classList.add('hidden');
                if (avatarUploadLabel) avatarUploadLabel.style.pointerEvents = '';
                avatarFileInput.value = '';
            });
        });
    }

    if (avatarDeleteBtn && deleteAvatarModal) {
        avatarDeleteBtn.addEventListener('click', function () {
            deleteAvatarModal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        });
    }

    if (avatarDeleteConfirmBtn) {
        avatarDeleteConfirmBtn.addEventListener('click', function () {
            avatarDeleteConfirmBtn.disabled = true;
            avatarDeleteConfirmBtn.textContent = 'Deleting\u2026';

            var formData = new URLSearchParams();
            formData.append('csrf_token', window.CSRF_TOKEN || (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute?.('content') || '');

            fetch(<?= json_encode(base_url('/settings/avatar/delete')) ?>, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData.toString()
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success) {
                    clearAvatarImg();
                    showToast('Avatar removed.', 'success');
                    if (deleteAvatarModal) {
                        deleteAvatarModal.classList.add('hidden');
                        if (!Array.from(document.querySelectorAll('[role="dialog"]')).some(function (el) {
                            return !el.classList.contains('hidden');
                        })) {
                            document.body.classList.remove('overflow-hidden');
                        }
                    }
                } else {
                    showToast('Could not remove avatar. Please try again.', 'error');
                }
            })
            .catch(function () {
                showToast('Could not remove avatar. Please try again.', 'error');
            })
            .finally(function () {
                avatarDeleteConfirmBtn.disabled = false;
                avatarDeleteConfirmBtn.textContent = 'Delete';
            });
        });
    }

    // ---- Copy invite code ----
    document.querySelectorAll('[data-copy-invite]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var code = btn.getAttribute('data-copy-value');
            if (code && navigator.clipboard) {
                navigator.clipboard.writeText(code).then(function () {
                    showToast('Invite code copied.', 'success');
                });
            }
        });
    });

    // ---- Announcement character counter ----
    var announcementInput = document.getElementById('cp_announcement_message');
    var announcementCounter = document.getElementById('cp_announcement_counter');
    if (announcementInput && announcementCounter) {
        var maxLen = Number(announcementInput.getAttribute('maxlength') || 1000);
        var updateAnnouncementCounter = function () {
            announcementCounter.textContent = String(announcementInput.value.length) + '/' + String(maxLen);
        };
        announcementInput.addEventListener('input', updateAnnouncementCounter);
        updateAnnouncementCounter();
    }

    // ---- Attachments select all/deselect all ----
    var attachBtn = document.getElementById('cp-attachments-select-all-btn');
    var attachForm = document.getElementById('cp-attachments-type-form');
    if (attachBtn && attachForm) {
        var getBoxes = function () { return Array.from(attachForm.querySelectorAll('input[type="checkbox"]')); };
        var allChecked = function () { return getBoxes().every(function (cb) { return cb.checked; }); };
        var updateAttachBtn = function () { attachBtn.textContent = allChecked() ? 'Deselect all' : 'Select all'; };
        updateAttachBtn();
        attachBtn.addEventListener('click', function () {
            var check = !allChecked();
            getBoxes().forEach(function (cb) { cb.checked = check; });
            updateAttachBtn();
        });
        attachForm.addEventListener('change', updateAttachBtn);
    }
})();
</script>
