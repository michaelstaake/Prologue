<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(base_url('/assets/img/favicon.png'), ENT_QUOTES, 'UTF-8') ?>">
    <title>Prologue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <script>
        tailwind.config = { content: ["**/*.{php,html}"], darkMode: 'class' };
    </script>
    <style>
        body { background: #09090b; }
        .prologue-accent { color: #34d399; }
        .sidebar { background: #111827; }
        .typing-dots { display: inline-flex; gap: 1px; margin-left: 2px; }
        .typing-dots span { opacity: .35; animation: typing-dot 1s infinite; }
        .typing-dots span:nth-child(2) { animation-delay: .2s; }
        .typing-dots span:nth-child(3) { animation-delay: .4s; }
        @keyframes typing-dot {
            0%, 80%, 100% { opacity: .35; }
            40% { opacity: 1; }
        }
        @keyframes notification-expire-bar {
            0% { transform: scaleX(1); }
            100% { transform: scaleX(0); }
        }
        @media (max-width: 1023px) {
            #app-sidebar { display: none; }
            #app-sidebar.mobile-open {
                display: flex;
                position: fixed;
                top: 0; left: 0; bottom: 0;
                width: min(85vw, 18rem);
                z-index: 50;
            }
            #notification-history-panel { display: none; }
            #notification-history-panel.mobile-open {
                display: flex;
                position: fixed;
                top: 0; right: 0; bottom: 0;
                width: min(95vw, 24rem);
                z-index: 50;
                border-left: 1px solid #3f3f46;
            }
            #mobile-overlay-backdrop { display: none; }
            #mobile-overlay-backdrop.visible {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.5);
                z-index: 49;
            }
        }
    </style>
</head>
<body class="h-screen text-gray-200 overflow-hidden">
    <?php $currentUser = Auth::user(); ?>
    <?php
        $requestPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $isChatRoute = preg_match('#(?:^|/)c/[^/]+$#', $requestPath) === 1;
        $mobileFabBottomClass = $isChatRoute ? 'bottom-44 sm:bottom-24' : 'bottom-5';
    ?>
    <?php
        $browserNotificationsEnabled = false;
        $friendRequestSoundEnabled = true;
        $newMessageSoundEnabled = true;
        $otherNotificationSoundEnabled = true;
        $outgoingCallRingSoundEnabled = true;
        $notificationSidebarExpanded = false;
        $userTimezone = 'UTC+0';
        if ($currentUser) {
            $notificationSetting = Database::query('SELECT `value` FROM settings WHERE `key` = ?', ['browser_notifications_' . $currentUser->id])->fetchColumn();
            $browserNotificationsEnabled = ((int)$notificationSetting) === 1;
            $friendRequestSoundSetting = Database::query('SELECT `value` FROM settings WHERE `key` = ?', ['sound_friend_request_' . $currentUser->id])->fetchColumn();
            $newMessageSoundSetting = Database::query('SELECT `value` FROM settings WHERE `key` = ?', ['sound_new_message_' . $currentUser->id])->fetchColumn();
            $otherNotificationSoundSetting = Database::query('SELECT `value` FROM settings WHERE `key` = ?', ['sound_other_notifications_' . $currentUser->id])->fetchColumn();
            $outgoingCallRingSoundSetting = Database::query('SELECT `value` FROM settings WHERE `key` = ?', ['sound_outgoing_call_ring_' . $currentUser->id])->fetchColumn();

            $friendRequestSoundEnabled = $friendRequestSoundSetting === false ? true : ((int)$friendRequestSoundSetting) === 1;
            $newMessageSoundEnabled = $newMessageSoundSetting === false ? true : ((int)$newMessageSoundSetting) === 1;
            $otherNotificationSoundEnabled = $otherNotificationSoundSetting === false ? true : ((int)$otherNotificationSoundSetting) === 1;
            $outgoingCallRingSoundEnabled = $outgoingCallRingSoundSetting === false ? true : ((int)$outgoingCallRingSoundSetting) === 1;

            $notificationSidebarSetting = Database::query('SELECT `value` FROM settings WHERE `key` = ?', ['notif_sidebar_expanded_' . $currentUser->id])->fetchColumn();
            $notificationSidebarExpanded = ((int)$notificationSidebarSetting) === 1;

            $userTimezoneSetting = Database::query('SELECT `value` FROM settings WHERE `key` = ?', ['timezone_' . $currentUser->id])->fetchColumn();
            $userTimezone = $userTimezoneSetting !== false ? (string)$userTimezoneSetting : 'UTC+0';

            if (isset($incomingRequestCount)) {
                $incomingFriendRequestCount = (int)$incomingRequestCount;
            } else {
                $incomingFriendRequestCount = (int)Database::query(
                    "SELECT COUNT(*) FROM friends WHERE friend_id = ? AND status = 'pending'",
                    [$currentUser->id]
                )->fetchColumn();
            }
        }
    ?>
    <?php if ($currentUser): ?>
    <div id="app-layout" class="flex h-screen">
        <aside id="app-sidebar" class="w-72 sidebar border-r border-zinc-800 flex flex-col">
            <a href="<?= htmlspecialchars(base_url('/'), ENT_QUOTES, 'UTF-8') ?>" class="p-4 border-b border-zinc-700 flex items-center gap-3 hover:bg-zinc-800/40 transition">
                <i class="fa-solid fa-comments text-2xl prologue-accent"></i>
                <span class="text-2xl font-bold">Prologue</span>
            </a>
            <div class="flex-1 overflow-auto p-4 space-y-4">

                <div>
                    <div class="px-3 pb-2 text-xs uppercase tracking-wide text-zinc-500">Private Chats</div>
                    <div id="private-chat-list" class="space-y-1"></div>
                </div>

                <div>
                    <div class="px-3 pb-2 text-xs uppercase tracking-wide text-zinc-500">Group Chats</div>
                    <div id="group-chat-list" class="space-y-1"></div>
                    <button type="button" onclick="createGroupChat()" class="w-full text-left mt-2 py-2 px-3 hover:bg-zinc-800 rounded-xl"><i class="fa fa-user-group"></i> New Group</button>
                </div>
            </div>
            <div class="p-4 border-t border-zinc-700 text-sm text-zinc-400">
                <?php $currentUserAvatarUrl = User::avatarUrl($currentUser); ?>
                <a href="<?= htmlspecialchars(base_url('/u/' . User::formatUserNumber($currentUser->user_number)), ENT_QUOTES, 'UTF-8') ?>" class="flex items-center gap-3 mb-2 hover:bg-zinc-800/40 rounded-xl p-2 -m-2 transition">
                    <?php if ($currentUserAvatarUrl): ?>
                        <img src="<?= htmlspecialchars($currentUserAvatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Your avatar" class="w-10 h-10 rounded-full object-cover border border-zinc-700">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-full border border-zinc-700 flex items-center justify-center font-semibold <?= htmlspecialchars(User::avatarColorClasses($currentUser->user_number), ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(User::avatarInitial($currentUser->username), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div class="font-medium text-zinc-200"><?= htmlspecialchars($currentUser->username, ENT_QUOTES, 'UTF-8') ?></div>
                        <div><?= htmlspecialchars(User::formatUserNumber($currentUser->user_number), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </a>
                <div class="mt-3 flex items-center gap-4">
                    <div class="relative">
                        <button type="button" id="status-menu-toggle" class="text-zinc-300 hover:text-zinc-100 inline-flex items-center gap-2">
                            <span class="flex items-center gap-2 <?= htmlspecialchars($currentUser->effective_status_text_class ?? User::presenceStatusTextClass($currentUser->effective_status ?? 'online'), ENT_QUOTES, 'UTF-8') ?>" id="sidebar-user-status">
                                <span class="inline-block w-2 h-2 rounded-full <?= htmlspecialchars($currentUser->effective_status_dot_class ?? User::presenceStatusDotClass($currentUser->effective_status ?? 'online'), ENT_QUOTES, 'UTF-8') ?>" id="sidebar-user-status-dot"></span>
                                <span id="sidebar-user-status-label"><?= htmlspecialchars($currentUser->effective_status_label ?? User::presenceStatusLabel($currentUser->effective_status ?? 'online'), ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                            <i class="fa fa-chevron-up text-[10px] text-zinc-500"></i>
                        </button>
                        <div id="status-menu" class="hidden absolute bottom-full mb-2 left-0 w-44 bg-zinc-900 border border-zinc-700 rounded-xl p-2 space-y-1 z-30">
                            <button type="button" class="w-full text-left px-3 py-2 rounded-lg hover:bg-zinc-800 flex items-center justify-between" data-status-choice="online">
                                <span class="flex items-center gap-2"><span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>Online</span>
                                <i class="fa fa-check text-xs text-emerald-400 hidden" data-status-check="online"></i>
                            </button>
                            <button type="button" class="w-full text-left px-3 py-2 rounded-lg hover:bg-zinc-800 flex items-center justify-between" data-status-choice="busy">
                                <span class="flex items-center gap-2"><span class="inline-block w-2 h-2 rounded-full bg-amber-500"></span>Busy</span>
                                <i class="fa fa-check text-xs text-amber-400 hidden" data-status-check="busy"></i>
                            </button>
                            <button type="button" class="w-full text-left px-3 py-2 rounded-lg hover:bg-zinc-800 flex items-center justify-between" data-status-choice="offline">
                                <span class="flex items-center gap-2"><span class="inline-block w-2 h-2 rounded-full bg-red-500"></span>Offline</span>
                                <i class="fa fa-check text-xs text-red-400 hidden" data-status-check="offline"></i>
                            </button>
                        </div>
                    </div>
                    <a href="<?= htmlspecialchars(base_url('/settings'), ENT_QUOTES, 'UTF-8') ?>" class="text-zinc-300 hover:text-zinc-100"><i class="fa fa-cog"></i> Settings</a>
                    <div onclick="logout()" class="cursor-pointer text-red-400 hover:text-red-300"><i class="fa fa-right-from-bracket"></i> Exit</div>
                </div>
            </div>
        </aside>

        <main class="flex-1 flex flex-col min-w-0">
            <?= $content ?>
        </main>

        <aside id="notification-history-panel" class="w-20 border-l border-zinc-800 bg-zinc-900/90 flex flex-col transition-all duration-200 ease-out">
            <div class="p-4 border-b border-zinc-700 flex items-center justify-center" id="notification-history-header">
                <div id="notification-history-title" class="hidden font-semibold text-zinc-100 mr-3">Notifications</div>
                <button id="notification-history-button" class="relative w-12 h-12 rounded-2xl bg-zinc-900 border border-zinc-700 hover:bg-zinc-800" aria-label="Toggle notification history">
                    <i class="fa-regular fa-bell"></i>
                    <span id="notification-history-count" class="hidden absolute -top-2 -right-2 min-w-[1.25rem] h-5 px-1 rounded-full bg-emerald-600 text-white text-xs flex items-center justify-center">0</span>
                </button>
            </div>
            <div id="notification-history-list" class="hidden flex-1 overflow-auto"></div>
        </aside>
    </div>

    <div id="mobile-overlay-backdrop"></div>

    <div class="lg:hidden fixed <?= $mobileFabBottomClass ?> left-5 z-30">
        <button id="sidebar-toggle-mobile" class="w-14 h-14 rounded-2xl bg-zinc-900 border border-zinc-700 hover:bg-zinc-800 shadow-lg flex items-center justify-center" aria-label="Open menu">
            <i class="fa-solid fa-bars text-lg"></i>
        </button>
    </div>

    <div class="lg:hidden fixed <?= $mobileFabBottomClass ?> right-5 z-30">
        <button id="notification-history-button-mobile" class="relative w-14 h-14 rounded-2xl bg-zinc-900 border border-zinc-700 hover:bg-zinc-800 shadow-lg flex items-center justify-center" aria-label="Open notifications">
            <i class="fa-regular fa-bell text-lg"></i>
            <span id="notification-history-count-mobile" class="hidden absolute -top-2 -right-2 min-w-[1.25rem] h-5 px-1 rounded-full bg-emerald-600 text-white text-xs flex items-center justify-center">0</span>
        </button>
    </div>

    <div id="report-modal" class="hidden fixed inset-0 bg-black/70 z-50 p-4 md:p-6" aria-hidden="true">
        <div class="h-full w-full flex items-center justify-center">
            <div class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-6" role="dialog" aria-modal="true" aria-labelledby="report-modal-title">
                <h2 id="report-modal-title" class="text-lg font-semibold text-zinc-100">Report content</h2>
                <p id="report-modal-description" class="mt-2 text-sm text-zinc-400">Help us understand the issue by sharing what happened.</p>
                <form id="report-form" class="mt-4 space-y-4">
                    <label for="report-reason-input" class="block text-sm text-zinc-300">Reason</label>
                    <textarea id="report-reason-input" rows="4" maxlength="200" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100 resize-y" placeholder="Describe the issue" required></textarea>
                    <div class="text-xs text-zinc-400 text-right" id="report-reason-counter" aria-live="polite">0/200</div>
                    <div class="flex items-center justify-end gap-3">
                        <button type="button" id="report-cancel" class="px-4 py-2 rounded-xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-200">Cancel</button>
                        <button type="submit" id="report-submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white">Submit report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <div class="inline-flex items-center gap-2 text-2xl font-bold">
                    <i class="fa-solid fa-comments prologue-accent"></i>
                    <span>Prologue</span>
                </div>
            </div>
            <?= $content ?>
        </div>
    </div>
    <?php endif; ?>
    <div id="toast-host" class="fixed top-20 right-5 space-y-2 z-40"></div>
</body>
<script>
    window.CSRF_TOKEN = <?= json_encode($csrf) ?>;
    window.BROWSER_NOTIFICATIONS_ENABLED = <?= $browserNotificationsEnabled ? 'true' : 'false' ?>;
    window.NOTIFICATION_SOUND_FRIEND_REQUEST_ENABLED = <?= $friendRequestSoundEnabled ? 'true' : 'false' ?>;
    window.NOTIFICATION_SOUND_NEW_MESSAGE_ENABLED = <?= $newMessageSoundEnabled ? 'true' : 'false' ?>;
    window.NOTIFICATION_SOUND_OTHER_ENABLED = <?= $otherNotificationSoundEnabled ? 'true' : 'false' ?>;
    window.NOTIFICATION_SOUND_OUTGOING_CALL_RING_ENABLED = <?= $outgoingCallRingSoundEnabled ? 'true' : 'false' ?>;
    window.NOTIFICATION_SIDEBAR_EXPANDED = <?= $notificationSidebarExpanded ? 'true' : 'false' ?>;
    window.USER_TIMEZONE = <?= json_encode($userTimezone) ?>;
</script>
<script src="<?= htmlspecialchars(base_url('/assets/js/notification.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(base_url('/assets/js/chat.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(base_url('/assets/js/call.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(base_url('/assets/js/user.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(base_url('/assets/js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</html>