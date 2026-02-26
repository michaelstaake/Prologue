<div class="p-8 overflow-auto space-y-6">
    <h1 class="text-3xl font-bold">Settings</h1>

    <?php
        $toastMessage = '';
        $toastKind = 'info';
        $currentAvatarUrl = User::avatarUrl($user);
        $autoOpenModalId = '';

        $flashSuccess = flash_get('success');
        $flashError = flash_get('error');

        if ($flashSuccess === 'email_saved') {
            $toastMessage = 'Email updated successfully.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'email_change_sent') {
            $toastMessage = 'Verification code sent to your new email address.';
            $toastKind = 'success';
            $autoOpenModalId = 'change-email-modal';
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
        } elseif ($flashSuccess === 'invite_deleted') {
            $toastMessage = 'Invite code deleted.';
            $toastKind = 'success';
        } elseif ($flashSuccess === 'session_exited') {
            $toastMessage = 'Session ended successfully.';
            $toastKind = 'success';
        } elseif ($flashError === 'invite_limit') {
            $toastMessage = 'Invite limit reached. You cannot generate more codes right now.';
            $toastKind = 'error';
        } elseif ($flashError === 'invite_disabled') {
            $toastMessage = 'Invite generation is currently disabled.';
            $toastKind = 'error';
        } elseif ($flashError === 'invite_delete_unavailable') {
            $toastMessage = 'This invite code cannot be deleted.';
            $toastKind = 'error';
        } elseif ($flashError === 'email_invalid') {
            $toastMessage = 'Please enter a valid email address.';
            $toastKind = 'error';
            $autoOpenModalId = 'change-email-modal';
        } elseif ($flashError === 'email_taken') {
            $toastMessage = 'That email address is already in use.';
            $toastKind = 'error';
            $autoOpenModalId = 'change-email-modal';
        } elseif ($flashError === 'email_change_code_invalid') {
            $toastMessage = 'Verification code is invalid.';
            $toastKind = 'error';
            $autoOpenModalId = 'change-email-modal';
        } elseif ($flashError === 'email_change_expired') {
            $toastMessage = 'Email change request expired. Please start again.';
            $toastKind = 'error';
            $autoOpenModalId = 'change-email-modal';
        } elseif ($flashError === 'password_current_invalid') {
            $toastMessage = 'Current password is incorrect.';
            $toastKind = 'error';
            $autoOpenModalId = 'change-password-modal';
        } elseif ($flashError === 'password_invalid') {
            $toastMessage = 'New password must be at least 8 characters.';
            $toastKind = 'error';
            $autoOpenModalId = 'change-password-modal';
        } elseif ($flashError === 'password_mismatch') {
            $toastMessage = 'Password confirmation does not match.';
            $toastKind = 'error';
            $autoOpenModalId = 'change-password-modal';
        } elseif ($flashError === 'username_invalid') {
            $toastMessage = 'Username must be 4-32 characters, start with a letter, and only contain lowercase letters and numbers.';
            $toastKind = 'error';
            $autoOpenModalId = 'change-username-modal';
        } elseif ($flashError === 'username_same') {
            $toastMessage = 'New username must be different from your current username.';
            $toastKind = 'error';
            $autoOpenModalId = 'change-username-modal';
        } elseif ($flashError === 'username_taken') {
            $toastMessage = 'That username is unavailable.';
            $toastKind = 'error';
            $autoOpenModalId = 'change-username-modal';
        } elseif ($flashError === 'username_cooldown') {
            $toastMessage = 'Username was changed recently. Please try again after the cooldown period.';
            $toastKind = 'error';
            $autoOpenModalId = 'change-username-modal';
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

    <?php if (strtolower((string)($user->role ?? '')) === 'admin'): ?>
        <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-4xl">
            <h2 class="text-xl font-semibold mb-4">Admin</h2>
            <div class="flex flex-wrap items-center gap-3">
                <a
                    href="<?= htmlspecialchars(base_url('/reports'), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center gap-2 <?= ((int)($pendingReportCount ?? 0) > 0) ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-zinc-700 hover:bg-zinc-600' ?> px-6 py-2 rounded-xl"
                >
                    <i class="fa-regular fa-flag"></i>
                    <span>Reports</span>
                    <span class="min-w-[1.25rem] h-5 px-1 rounded-full bg-zinc-900/70 border border-emerald-300/50 text-white text-xs inline-flex items-center justify-center <?= ((int)($pendingReportCount ?? 0) > 0) ? '' : 'hidden' ?>">
                        <?= htmlspecialchars((string)min(99, (int)($pendingReportCount ?? 0)) . (((int)($pendingReportCount ?? 0) > 99) ? '+' : ''), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </a>
                <a
                    href="<?= htmlspecialchars(base_url('/users'), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center gap-2 bg-zinc-700 hover:bg-zinc-600 px-6 py-2 rounded-xl"
                >
                    <i class="fa-solid fa-user-shield"></i>
                    <span>Users</span>
                </a>
                <a
                    href="<?= htmlspecialchars(base_url('/tree'), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center gap-2 bg-zinc-700 hover:bg-zinc-600 px-6 py-2 rounded-xl"
                >
                    <i class="fa-solid fa-diagram-project"></i>
                    <span>Tree</span>
                </a>
                <a
                    href="<?= htmlspecialchars(base_url('/config'), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center gap-2 bg-zinc-700 hover:bg-zinc-600 px-6 py-2 rounded-xl"
                >
                    <i class="fa-solid fa-sliders"></i>
                    <span>Config</span>
                </a>
                <a
                    href="<?= htmlspecialchars(base_url('/trash'), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center gap-2 bg-zinc-700 hover:bg-zinc-600 px-6 py-2 rounded-xl"
                >
                    <i class="fa-regular fa-trash-can"></i>
                    <span>Trash</span>
                </a>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($invitesEnabled)): ?>
        <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-4xl">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <h2 class="text-xl font-semibold">Invites</h2>
                <form method="POST" action="<?= htmlspecialchars(base_url('/settings/invites/generate'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button
                        type="submit"
                        class="bg-emerald-600 hover:bg-emerald-500 px-4 py-2 rounded-lg disabled:bg-zinc-700 disabled:text-zinc-400 disabled:cursor-not-allowed"
                        <?= $inviteCount >= $inviteLimit ? 'disabled' : '' ?>
                    >
                        Generate invite code
                    </button>
                </form>
            </div>
            <p class="text-sm text-zinc-400 mb-4">Generated: <?= (int)$inviteCount ?> / <?= (int)$inviteLimit ?></p>
            <div class="space-y-2 text-sm">
                <?php if (empty($invites)): ?>
                    <p class="text-zinc-400">No invite codes available.</p>
                <?php else: ?>
                    <?php foreach ($invites as $invite): ?>
                        <div class="flex items-center justify-between gap-3 bg-zinc-800 rounded-lg px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span class="font-mono"><?= htmlspecialchars($invite->code, ENT_QUOTES, 'UTF-8') ?></span>
                                <button
                                    type="button"
                                    class="text-zinc-400 hover:text-zinc-200"
                                    title="Copy to clipboard"
                                    aria-label="Copy to clipboard"
                                    data-copy-invite
                                    data-copy-value="<?= htmlspecialchars($invite->code, ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                                <?php if (!$invite->used_by): ?>
                                    <form method="POST" action="<?= htmlspecialchars(base_url('/settings/invites/delete'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Delete this invite code?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="invite_code" value="<?= htmlspecialchars($invite->code, ENT_QUOTES, 'UTF-8') ?>">
                                        <button
                                            type="submit"
                                            class="text-zinc-500 hover:text-red-300"
                                            title="Delete invite code"
                                            aria-label="Delete invite code"
                                        >
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
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
        </section>
    <?php endif; ?>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-4xl">
        <h2 class="text-xl font-semibold mb-4">Account</h2>
        <?php
            $pendingEmailValue = !empty($pendingEmailChange) ? (string)$pendingEmailChange->new_email : (string)$user->email;
            $pendingEmailSeconds = !empty($pendingEmailChange) ? max(0, (int)$pendingEmailChange->seconds_remaining) : 0;
            $pendingEmailMinutes = (int) ceil($pendingEmailSeconds / 60);
            if ($pendingEmailMinutes < 1) {
                $pendingEmailMinutes = 1;
            }
        ?>
        <div class="space-y-6">
            <div>
                <p class="text-sm text-zinc-400 mb-1">Current email</p>
                <p class="text-zinc-100"><?= htmlspecialchars($user->email, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <?php if (!empty($pendingEmailChange)): ?>
                <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
                    Email change pending for <span class="font-semibold"><?= htmlspecialchars($pendingEmailChange->new_email, ENT_QUOTES, 'UTF-8') ?></span>.
                    Enter the verification code within <?= (int)$pendingEmailMinutes ?> minute<?= $pendingEmailMinutes === 1 ? '' : 's' ?>.
                    <button type="button" data-modal-open="change-email-modal" class="ml-1 underline decoration-emerald-400 text-emerald-300 hover:text-emerald-200">Enter code</button>
                </div>
            <?php endif; ?>
            <button
                type="button"
                data-modal-open="change-email-modal"
                class="bg-emerald-600 hover:bg-emerald-500 px-6 py-2 rounded-xl"
            >
                Change email
            </button>
            <button
                type="button"
                data-modal-open="change-password-modal"
                class="bg-emerald-600 hover:bg-emerald-500 px-6 py-2 rounded-xl"
            >
                Change password
            </button>
        </div>
    </section>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-4xl">
        <h2 class="text-xl font-semibold mb-4">Profile</h2>
        <div class="space-y-4">
            <div>
                <p class="text-sm text-zinc-400 mb-1">Current username</p>
                <p class="text-zinc-100"><?= htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <?php if (!$usernameCanChangeNow && !empty($usernameChangeAvailableAt)): ?>
                <p class="text-sm text-amber-300">username recently changed. You can change it again on <?= htmlspecialchars($usernameChangeAvailableAt, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <button
                type="button"
                data-modal-open="change-username-modal"
                <?= $usernameCanChangeNow ? '' : 'disabled' ?>
                class="bg-emerald-600 hover:bg-emerald-500 px-6 py-2 rounded-xl disabled:bg-zinc-700 disabled:text-zinc-400 disabled:cursor-not-allowed"
            >
                Change username
            </button>
        </div>
    </section>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-4xl">
        <h2 class="text-xl font-semibold mb-4">Avatar</h2>
        <div class="border border-zinc-700 rounded-xl p-4 bg-zinc-800/40">
            <p class="text-sm text-zinc-400 mb-4">JPG or PNG. Images are resized to 256 &times; 256. Maximum 2048 &times; 2048 pixels.</p>
            <div class="flex items-center gap-5">
                <div
                    id="avatar-preview-wrap"
                    class="relative shrink-0"
                    data-initial="<?= htmlspecialchars(User::avatarInitial($user->username), ENT_QUOTES, 'UTF-8') ?>"
                    data-initials-class="w-16 h-16 rounded-full border border-zinc-700 flex items-center justify-center text-xl font-semibold <?= htmlspecialchars(User::avatarColorClasses($user->user_number), ENT_QUOTES, 'UTF-8') ?>"
                >
                    <?php if ($currentAvatarUrl): ?>
                        <img
                            id="avatar-preview-img"
                            src="<?= htmlspecialchars($currentAvatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                            alt="Your avatar"
                            class="w-16 h-16 rounded-full object-cover border border-zinc-700"
                        >
                    <?php else: ?>
                        <div
                            id="avatar-preview-initials"
                            class="w-16 h-16 rounded-full border border-zinc-700 flex items-center justify-center text-xl font-semibold <?= htmlspecialchars(User::avatarColorClasses($user->user_number), ENT_QUOTES, 'UTF-8') ?>"
                        >
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
                    <?php if ($currentAvatarUrl): ?>
                        <button
                            id="avatar-delete-btn"
                            type="button"
                            class="px-4 py-2 rounded-lg text-sm bg-zinc-800 border border-zinc-600 hover:border-red-500 hover:text-red-400 text-zinc-300"
                        >
                            Delete avatar
                        </button>
                    <?php else: ?>
                        <button
                            id="avatar-delete-btn"
                            type="button"
                            class="hidden px-4 py-2 rounded-lg text-sm bg-zinc-800 border border-zinc-600 hover:border-red-500 hover:text-red-400 text-zinc-300"
                        >
                            Delete avatar
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-4xl">
        <h2 class="text-xl font-semibold mb-4">Notifications</h2>
        <p class="text-sm text-zinc-400 mb-5">Changes save automatically.</p>
        <div class="space-y-4" id="notification-settings-toggles">
            <label class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                <span class="text-zinc-100">Browser notifications</span>
                <input
                    type="checkbox"
                    data-notification-setting="browser_notifications"
                    <?= $browserNotif ? 'checked' : '' ?>
                    class="w-6 h-6 accent-emerald-500"
                >
            </label>
            <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                <span class="text-zinc-100">Sound: New friend request</span>
                <div class="flex items-center gap-3">
                    <button type="button" data-notification-sound-preview="friend_request" class="px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Preview</button>
                    <input
                        type="checkbox"
                        data-notification-setting="sound_friend_request"
                        <?= $friendRequestSoundNotif ? 'checked' : '' ?>
                        class="w-6 h-6 accent-emerald-500"
                    >
                </div>
            </div>
            <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                <span class="text-zinc-100">Sound: New chat message</span>
                <div class="flex items-center gap-3">
                    <button type="button" data-notification-sound-preview="new_message" class="px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Preview</button>
                    <input
                        type="checkbox"
                        data-notification-setting="sound_new_message"
                        <?= $newMessageSoundNotif ? 'checked' : '' ?>
                        class="w-6 h-6 accent-emerald-500"
                    >
                </div>
            </div>
            <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                <span class="text-zinc-100">Sound: Other notifications</span>
                <div class="flex items-center gap-3">
                    <button type="button" data-notification-sound-preview="other" class="px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Preview</button>
                    <input
                        type="checkbox"
                        data-notification-setting="sound_other_notifications"
                        <?= $otherNotificationSoundNotif ? 'checked' : '' ?>
                        class="w-6 h-6 accent-emerald-500"
                    >
                </div>
            </div>
            <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3">
                <span class="text-zinc-100">Sound: Outgoing call ringing</span>
                <div class="flex items-center gap-3">
                    <button type="button" data-notification-sound-preview="call" class="px-3 py-1.5 text-xs rounded-lg bg-zinc-700 hover:bg-zinc-600 text-zinc-100">Preview</button>
                    <input
                        type="checkbox"
                        data-notification-setting="sound_outgoing_call_ring"
                        <?= $outgoingCallRingSoundNotif ? 'checked' : '' ?>
                        class="w-6 h-6 accent-emerald-500"
                    >
                </div>
            </div>
        </div>
        <p id="notification-settings-status" class="mt-3 text-xs text-zinc-500" aria-live="polite"></p>
    </section>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-4xl">
        <h2 class="text-xl font-semibold mb-4">Time Zone</h2>
        <form method="POST" action="<?= htmlspecialchars(base_url('/settings/timezone'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div class="border border-zinc-700 rounded-xl p-4 bg-zinc-800/40">
                <p class="text-sm text-zinc-300 mb-3">All times are stored in UTC. Select your local time zone to display them correctly.</p>
                <select
                    name="timezone"
                    class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                >
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
            </div>
            <button type="submit" class="mt-6 bg-emerald-600 hover:bg-emerald-500 px-8 py-3 rounded-xl">Save time zone</button>
        </form>
    </section>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-4xl">
        <h2 class="text-xl font-semibold mb-4">Sessions</h2>
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
    </section>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-6 max-w-4xl">
        <h2 class="text-xl font-semibold mb-4">Prologue <?= htmlspecialchars((string)APP_VERSION, ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="space-y-3 text-sm">
            <a href="<?= htmlspecialchars(base_url('/info'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 text-emerald-400 hover:text-emerald-300 underline decoration-emerald-500">
                System info
            </a>
        </div>
    </section>
</div>

<?php if ($autoOpenModalId !== ''): ?>
    <div id="settings-modal-autoload" data-modal-id="<?= htmlspecialchars($autoOpenModalId, ENT_QUOTES, 'UTF-8') ?>" class="hidden" aria-hidden="true"></div>
<?php endif; ?>

<div id="delete-avatar-modal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="delete-avatar-title">
    <div class="absolute inset-0 bg-black/70" data-modal-close="delete-avatar-modal"></div>
    <div class="relative z-10 flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-sm rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 id="delete-avatar-title" class="text-xl font-semibold">Delete avatar?</h3>
                <button type="button" data-modal-close="delete-avatar-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">✕</button>
            </div>
            <p class="text-sm text-zinc-300 mb-6">Your avatar will be removed and replaced with your initials.</p>
            <div class="flex gap-3">
                <button id="avatar-delete-confirm-btn" type="button" class="bg-red-600 hover:bg-red-500 px-6 py-2 rounded-xl text-sm">Delete</button>
                <button type="button" data-modal-close="delete-avatar-modal" class="bg-zinc-700 hover:bg-zinc-600 px-6 py-2 rounded-xl text-sm">Cancel</button>
            </div>
        </div>
    </div>
</div>

<div id="change-email-modal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="change-email-title">
    <div class="absolute inset-0 bg-black/70" data-modal-close="change-email-modal"></div>
    <div class="relative z-10 flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-lg rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 id="change-email-title" class="text-xl font-semibold">Change email</h3>
                <button type="button" data-modal-close="change-email-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">✕</button>
            </div>

            <form method="POST" action="<?= htmlspecialchars(base_url('/settings/account/email'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <label class="block text-sm text-zinc-300">New email address</label>
                <input
                    type="email"
                    name="email"
                    id="change-email-input"
                    data-current-email="<?= htmlspecialchars((string)$user->email, ENT_QUOTES, 'UTF-8') ?>"
                    value="<?= htmlspecialchars($pendingEmailValue, ENT_QUOTES, 'UTF-8') ?>"
                    <?= empty($pendingEmailChange) ? 'data-modal-autofocus' : '' ?>
                    inputmode="email"
                    autocomplete="email"
                    required
                    class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                >
                <p class="text-xs text-zinc-400">We send a 6-digit verification code to the new address. Code expires in 10 minutes.</p>
                <div class="mt-4 flex gap-2">
                    <button type="submit" id="change-email-send-button" class="bg-emerald-600 hover:bg-emerald-500 px-6 py-2 rounded-xl">Send verification code</button>
                    <button type="button" data-modal-close="change-email-modal" class="bg-zinc-700 hover:bg-zinc-600 px-6 py-2 rounded-xl">Close</button>
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
                        <button type="button" data-modal-close="change-email-modal" class="bg-zinc-700 hover:bg-zinc-600 px-6 py-2 rounded-xl">Cancel</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="change-password-modal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="change-password-title">
    <div class="absolute inset-0 bg-black/70" data-modal-close="change-password-modal"></div>
    <div class="relative z-10 flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-lg rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 id="change-password-title" class="text-xl font-semibold">Change password</h3>
                <button type="button" data-modal-close="change-password-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">✕</button>
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
                    <button type="button" data-modal-close="change-password-modal" class="bg-zinc-700 hover:bg-zinc-600 px-6 py-2 rounded-xl">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="change-username-modal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="change-username-title">
    <div class="absolute inset-0 bg-black/70" data-modal-close="change-username-modal"></div>
    <div class="relative z-10 flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-lg rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 id="change-username-title" class="text-xl font-semibold">Change username</h3>
                <button type="button" data-modal-close="change-username-modal" class="rounded-lg px-2 py-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">✕</button>
            </div>
            <form method="POST" action="<?= htmlspecialchars(base_url('/settings/profile/username'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <label class="block text-sm text-zinc-300">New username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') ?>" minlength="4" maxlength="32" pattern="[a-z][a-z0-9]{3,31}" autocapitalize="none" spellcheck="false" data-modal-autofocus required class="w-full rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <div class="mt-4 flex gap-2">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 px-6 py-2 rounded-xl">Save username</button>
                    <button type="button" data-modal-close="change-username-modal" class="bg-zinc-700 hover:bg-zinc-600 px-6 py-2 rounded-xl">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        const openButtons = document.querySelectorAll('[data-modal-open]');
        const closeButtons = document.querySelectorAll('[data-modal-close]');
        const changeEmailModal = document.getElementById('change-email-modal');
        const changeEmailInput = document.getElementById('change-email-input');
        const changeEmailSendButton = document.getElementById('change-email-send-button');

        const updateEmailSendButtonState = function () {
            if (!changeEmailInput || !changeEmailSendButton) return;

            const enteredEmail = String(changeEmailInput.value || '').trim().toLowerCase();
            const currentEmail = String(changeEmailInput.getAttribute('data-current-email') || '').trim().toLowerCase();
            const isDifferentEmail = enteredEmail !== '' && enteredEmail !== currentEmail;
            const isValidEmail = typeof changeEmailInput.checkValidity === 'function'
                ? changeEmailInput.checkValidity()
                : /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(enteredEmail);
            const canSend = isDifferentEmail && isValidEmail;

            changeEmailSendButton.disabled = !canSend;
            changeEmailSendButton.classList.toggle('hidden', !canSend);
        };

        const openModal = function (modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');

            if (modalId === 'change-email-modal') {
                updateEmailSendButtonState();
            }

            const autofocusInput = modal.querySelector('[data-modal-autofocus]');
            if (autofocusInput && typeof autofocusInput.focus === 'function') {
                setTimeout(function () {
                    autofocusInput.focus();
                    if (typeof autofocusInput.select === 'function') {
                        autofocusInput.select();
                    }
                }, 0);
            }
        };

        const closeModal = function (modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.classList.add('hidden');

            if (!Array.from(document.querySelectorAll('[role="dialog"]')).some(function (el) {
                return !el.classList.contains('hidden');
            })) {
                document.body.classList.remove('overflow-hidden');
            }
        };

        openButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const modalId = button.getAttribute('data-modal-open');
                if (modalId) {
                    openModal(modalId);
                }
            });
        });

        closeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const modalId = button.getAttribute('data-modal-close');
                if (modalId) {
                    closeModal(modalId);
                }
            });
        });

        if (changeEmailInput && changeEmailModal) {
            changeEmailInput.addEventListener('input', updateEmailSendButtonState);
            changeEmailInput.addEventListener('change', updateEmailSendButtonState);

            const changeEmailForm = changeEmailInput.closest('form');
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

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;

            document.querySelectorAll('[role="dialog"]').forEach(function (modal) {
                if (!modal.classList.contains('hidden')) {
                    modal.classList.add('hidden');
                }
            });

            document.body.classList.remove('overflow-hidden');
        });

        const autoloadModal = document.getElementById('settings-modal-autoload');
        if (autoloadModal) {
            const modalId = autoloadModal.getAttribute('data-modal-id');
            if (modalId) {
                openModal(modalId);
            }
        }
    })();
</script>

<script>
    (function () {
        const avatarFileInput = document.getElementById('avatar-file-input');
        const avatarPreviewWrap = document.getElementById('avatar-preview-wrap');
        const avatarUploadSpinner = document.getElementById('avatar-upload-spinner');
        const avatarUploadLabel = document.getElementById('avatar-upload-label');
        const avatarUploadLabelText = document.getElementById('avatar-upload-label-text');
        const avatarDeleteBtn = document.getElementById('avatar-delete-btn');
        const avatarDeleteConfirmBtn = document.getElementById('avatar-delete-confirm-btn');
        const deleteAvatarModal = document.getElementById('delete-avatar-modal');

        function getAvatarInitials() {
            const el = document.getElementById('avatar-preview-initials');
            return el || null;
        }

        function setAvatarImg(url) {
            let img = document.getElementById('avatar-preview-img');
            const initials = getAvatarInitials();

            if (initials) {
                initials.remove();
            }

            if (!img) {
                img = document.createElement('img');
                img.id = 'avatar-preview-img';
                img.alt = 'Your avatar';
                img.className = 'w-16 h-16 rounded-full object-cover border border-zinc-700';
                avatarPreviewWrap.insertBefore(img, avatarUploadSpinner);
            }

            img.src = url;
            avatarUploadLabelText.textContent = 'Change avatar';

            if (avatarDeleteBtn) {
                avatarDeleteBtn.classList.remove('hidden');
            }
        }

        function clearAvatarImg() {
            const img = document.getElementById('avatar-preview-img');
            if (img) {
                img.remove();
            }

            if (!getAvatarInitials()) {
                const div = document.createElement('div');
                div.id = 'avatar-preview-initials';
                div.className = avatarPreviewWrap.dataset.initialsClass || 'w-16 h-16 rounded-full border border-zinc-700 flex items-center justify-center text-xl font-semibold bg-emerald-700 text-emerald-100';
                div.textContent = avatarPreviewWrap.dataset.initial || '?';
                avatarPreviewWrap.insertBefore(div, avatarUploadSpinner);
            }

            avatarUploadLabelText.textContent = 'Upload avatar';

            if (avatarDeleteBtn) {
                avatarDeleteBtn.classList.add('hidden');
            }
        }

        if (avatarFileInput) {
            avatarFileInput.addEventListener('change', function () {
                const file = avatarFileInput.files && avatarFileInput.files[0];
                if (!file) return;

                const allowed = ['image/jpeg', 'image/png'];
                if (!allowed.includes(file.type)) {
                    showToast('Avatar must be a JPG or PNG image.', 'error');
                    avatarFileInput.value = '';
                    return;
                }

                const formData = new FormData();
                formData.append('csrf_token', window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
                formData.append('avatar', file);

                if (avatarUploadSpinner) avatarUploadSpinner.classList.remove('hidden');
                if (avatarUploadLabel) avatarUploadLabel.style.pointerEvents = 'none';

                fetch(<?= json_encode(base_url('/settings/avatar')) ?>, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.success && data.avatar_url) {
                        setAvatarImg(data.avatar_url);
                        showToast('Avatar updated.', 'success');
                    } else {
                        const errorMap = {
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

                const formData = new URLSearchParams();
                formData.append('csrf_token', window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

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
                            document.body.classList.remove('overflow-hidden');
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

    })();
</script>