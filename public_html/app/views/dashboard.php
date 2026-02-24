<div class="p-8 overflow-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold">Dashboard</h1>
    </div>

    <section class="max-w-4xl mb-6">
        <h2 class="text-xl font-semibold text-zinc-300 mb-3">Groups</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <?php if (!empty($lastGroupChat)): ?>
                <a href="<?= htmlspecialchars(base_url('/c/' . User::formatUserNumber((string)$lastGroupChat->chat_number)), ENT_QUOTES, 'UTF-8') ?>" class="block bg-zinc-800 rounded-xl p-3 hover:bg-zinc-700 transition">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-full border border-zinc-700 flex items-center justify-center bg-zinc-900 text-zinc-300">
                            <i class="fa fa-user-group text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="font-medium truncate"><?= htmlspecialchars((string)$lastGroupChat->chat_title, ENT_QUOTES, 'UTF-8') ?></div>
                            <p class="text-xs text-zinc-400 mt-1">Your most recently active group</p>
                        </div>
                    </div>
                </a>
            <?php endif; ?>

            <button type="button" onclick="createGroupChat()" class="w-full bg-zinc-800 hover:bg-zinc-700 rounded-xl p-3 flex items-center gap-3 transition">
                <div class="w-9 h-9 rounded-full border border-zinc-700 flex items-center justify-center bg-zinc-900 text-zinc-300">
                    <i class="fa fa-user-group text-sm"></i>
                </div>
                <div class="min-w-0 text-left">
                    <span class="font-medium block">New Group</span>
                    <p class="text-xs text-zinc-400 mt-1">Make something awesome</p>
                </div>
            </button>
        </div>
    </section>

    <h2 class="text-xl font-semibold text-zinc-300 mb-4">Friends</h2>

    <?php
        $toastMessage = '';
        $toastKind = 'error';
        $flashError = flash_get('error');
        if ($flashError === 'invalid_chat') {
            $toastMessage = 'That chat was not found.';
        } elseif ($flashError === 'user_not_found') {
            $toastMessage = 'That user was not found.';
        }
    ?>
    <?php if ($toastMessage !== ''): ?>
        <div id="page-toast" data-toast-message="<?= htmlspecialchars($toastMessage, ENT_QUOTES, 'UTF-8') ?>" data-toast-kind="<?= htmlspecialchars($toastKind, ENT_QUOTES, 'UTF-8') ?>" class="hidden" aria-hidden="true"></div>
    <?php endif; ?>

    <?php
        $tab = $selectedTab ?? 'all';
        $requestsTab = $selectedRequestsTab ?? 'incoming';
        $friendList = $visibleFriends ?? $friends ?? [];
        $incomingRequestCount = (int)($incomingRequestCount ?? count($pendingIncoming ?? []));
        $tabClass = static function($isActive) {
            return $isActive
                ? 'bg-emerald-600 text-white border-emerald-500'
                : 'bg-zinc-900 text-zinc-300 border-zinc-700 hover:bg-zinc-800';
        };
    ?>

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="<?= htmlspecialchars(base_url('/?tab=all'), ENT_QUOTES, 'UTF-8') ?>" class="px-5 py-2.5 rounded-xl border transition <?= htmlspecialchars($tabClass($tab === 'all'), ENT_QUOTES, 'UTF-8') ?>">All</a>
        <a href="<?= htmlspecialchars(base_url('/?tab=favorites'), ENT_QUOTES, 'UTF-8') ?>" class="px-5 py-2.5 rounded-xl border transition <?= htmlspecialchars($tabClass($tab === 'favorites'), ENT_QUOTES, 'UTF-8') ?>">Favorites</a>
        <a href="<?= htmlspecialchars(base_url('/?tab=online'), ENT_QUOTES, 'UTF-8') ?>" class="px-5 py-2.5 rounded-xl border transition <?= htmlspecialchars($tabClass($tab === 'online'), ENT_QUOTES, 'UTF-8') ?>">Online</a>
        <a href="<?= htmlspecialchars(base_url('/?tab=search'), ENT_QUOTES, 'UTF-8') ?>" class="px-5 py-2.5 rounded-xl border transition <?= htmlspecialchars($tabClass($tab === 'search'), ENT_QUOTES, 'UTF-8') ?>">Search</a>
        <a href="<?= htmlspecialchars(base_url('/?tab=requests'), ENT_QUOTES, 'UTF-8') ?>" class="px-5 py-2.5 rounded-xl border transition <?= htmlspecialchars($tabClass($tab === 'requests'), ENT_QUOTES, 'UTF-8') ?>">
            <span class="inline-flex items-center gap-2">
                <span>Requests</span>
                <span
                    id="friends-requests-tab-badge"
                    class="min-w-[1.25rem] h-5 px-1 rounded-full bg-emerald-600 text-white text-xs inline-flex items-center justify-center <?= $incomingRequestCount > 0 ? '' : 'hidden' ?>"
                ><?= (int)$incomingRequestCount ?></span>
            </span>
        </a>
    </div>

    <?php if ($tab === 'requests'): ?>
        <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-5 max-w-3xl">
            <div class="text-sm font-semibold text-zinc-300 mb-3">Incoming</div>
            <div class="space-y-3 mb-6">
                <?php if (empty($pendingIncoming)): ?>
                    <p class="text-zinc-400 text-sm">No incoming requests.</p>
                <?php else: ?>
                    <?php foreach ($pendingIncoming as $request): ?>
                        <?php $requestAvatar = User::avatarUrl($request); ?>
                        <div class="bg-zinc-800 rounded-xl p-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <?php if ($requestAvatar): ?>
                                        <img src="<?= htmlspecialchars($requestAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($request->username, ENT_QUOTES, 'UTF-8') ?> avatar" class="w-9 h-9 rounded-full object-cover border border-zinc-700">
                                    <?php else: ?>
                                        <div class="w-9 h-9 rounded-full border border-zinc-700 flex items-center justify-center font-semibold text-sm <?= htmlspecialchars(User::avatarColorClasses($request->user_number), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(User::avatarInitial($request->username), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="min-w-0">
                                        <div class="font-medium truncate"><?= htmlspecialchars($request->username, ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-zinc-400"><?= htmlspecialchars(User::formatUserNumber($request->user_number), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs <?= htmlspecialchars($request->effective_status_text_class ?? 'text-zinc-500', ENT_QUOTES, 'UTF-8') ?> mt-0.5">
                                            <?= htmlspecialchars($request->effective_status_label ?? 'Offline', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>
                                </div>
                                <button class="bg-emerald-600 hover:bg-emerald-500 text-sm px-3 py-1.5 rounded-lg" onclick="acceptFriendRequest(<?= (int)$request->requester_id ?>)">Accept</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="text-sm font-semibold text-zinc-300 mb-3">Outgoing</div>
            <div class="space-y-3">
                <?php if (empty($pendingOutgoing)): ?>
                    <p class="text-zinc-400 text-sm">No outgoing requests.</p>
                <?php else: ?>
                    <?php foreach ($pendingOutgoing as $request): ?>
                        <?php $requestAvatar = User::avatarUrl($request); ?>
                        <div class="bg-zinc-800 rounded-xl p-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <?php if ($requestAvatar): ?>
                                        <img src="<?= htmlspecialchars($requestAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($request->username, ENT_QUOTES, 'UTF-8') ?> avatar" class="w-9 h-9 rounded-full object-cover border border-zinc-700">
                                    <?php else: ?>
                                        <div class="w-9 h-9 rounded-full border border-zinc-700 flex items-center justify-center font-semibold text-sm <?= htmlspecialchars(User::avatarColorClasses($request->user_number), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(User::avatarInitial($request->username), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="min-w-0">
                                        <div class="font-medium truncate"><?= htmlspecialchars($request->username, ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-zinc-400"><?= htmlspecialchars(User::formatUserNumber($request->user_number), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs <?= htmlspecialchars($request->effective_status_text_class ?? 'text-zinc-500', ENT_QUOTES, 'UTF-8') ?> mt-0.5">
                                            <?= htmlspecialchars($request->effective_status_label ?? 'Offline', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>
                                </div>
                                <button class="bg-zinc-700 hover:bg-zinc-600 text-sm px-3 py-1.5 rounded-lg" onclick="cancelFriendRequest(<?= (int)($request->target_user_id ?? 0) ?>)">Cancel</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    <?php elseif ($tab === 'search'): ?>
        <section class="max-w-3xl">
            <form id="user-search-form" class="flex items-center gap-3 w-full mb-4">
                <input type="text" id="user-search-input" placeholder="Search users" class="bg-zinc-900 border border-zinc-700 rounded-xl px-5 py-2.5 w-full" pattern="[A-Za-z0-9-]+" title="Use only letters, numbers, and dashes." required>
                <button type="submit" class="bg-zinc-700 hover:bg-zinc-600 border border-zinc-700 px-5 py-2.5 rounded-xl">Search</button>
            </form>

            <div id="user-search-results" class="space-y-2"></div>
        </section>
    <?php else: ?>
        <section class="max-w-4xl">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php if (empty($friendList)): ?>
                    <p class="text-zinc-400 text-sm">No friends in this list.</p>
                <?php else: ?>
                    <?php foreach ($friendList as $friend): ?>
                        <?php $friendAvatar = User::avatarUrl($friend); ?>
                        <a href="<?= htmlspecialchars(base_url('/u/' . User::formatUserNumber($friend->user_number)), ENT_QUOTES, 'UTF-8') ?>" class="block bg-zinc-800 rounded-xl p-3 hover:bg-zinc-700 transition">
                            <div class="flex items-center gap-3 min-w-0">
                                <?php if ($friendAvatar): ?>
                                    <img src="<?= htmlspecialchars($friendAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($friend->username, ENT_QUOTES, 'UTF-8') ?> avatar" class="w-9 h-9 rounded-full object-cover border border-zinc-700">
                                <?php else: ?>
                                    <div class="w-9 h-9 rounded-full border border-zinc-700 flex items-center justify-center font-semibold text-sm <?= htmlspecialchars(User::avatarColorClasses($friend->user_number), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(User::avatarInitial($friend->username), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <div class="font-medium truncate flex items-center gap-2">
                                        <span class="truncate"><?= htmlspecialchars($friend->username, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ((int)($friend->is_favorite ?? 0) === 1): ?>
                                            <i class="fa-solid fa-star text-amber-400 text-[10px]" title="Favorite"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-zinc-400"><?= htmlspecialchars(User::formatUserNumber($friend->user_number), ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-xs <?= htmlspecialchars($friend->effective_status_text_class ?? 'text-zinc-500', ENT_QUOTES, 'UTF-8') ?> mt-0.5">
                                        <?= htmlspecialchars($friend->effective_status_label ?? 'Offline', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>