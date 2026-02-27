<div class="p-8 overflow-auto">
    <?php $inviteReferralPromptData = is_array($inviteReferralPrompt ?? null) ? $inviteReferralPrompt : null; ?>

    <?php if ($inviteReferralPromptData): ?>
        <div
            id="invite-referral-modal"
            class="hidden fixed inset-0 bg-black/70 z-50 p-4 md:p-6"
            aria-hidden="true"
            data-referrer-id="<?= (int)$inviteReferralPromptData['user_id'] ?>"
            data-referrer-username="<?= htmlspecialchars((string)$inviteReferralPromptData['username'], ENT_QUOTES, 'UTF-8') ?>"
            data-referrer-user-number="<?= htmlspecialchars((string)$inviteReferralPromptData['user_number'], ENT_QUOTES, 'UTF-8') ?>"
            data-auto-open="1"
        >
            <div class="h-full w-full flex items-center justify-center">
                <div class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-6" role="dialog" aria-modal="true" aria-labelledby="invite-referral-modal-title">
                    <h2 id="invite-referral-modal-title" class="text-lg font-semibold text-zinc-100">You were referred by <?= htmlspecialchars((string)$inviteReferralPromptData['username'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="mt-2 text-sm text-zinc-400">Would you like to send them a friend request now?</p>
                    <div class="mt-5 flex items-center justify-end gap-3">
                        <button type="button" id="invite-referral-modal-skip" class="px-4 py-2 rounded-xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-200">Not now</button>
                        <button type="button" id="invite-referral-modal-send" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white">Send Request</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="mb-6 flex items-center justify-between gap-3">
        <h1 class="text-3xl font-bold">Dashboard</h1>
        <a href="<?= htmlspecialchars(base_url('/search'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-zinc-700 text-zinc-300 hover:bg-zinc-800 transition">
            <i class="fa fa-magnifying-glass text-xs"></i>
            Search
        </a>
    </div>

    <?php $dashboardAnnouncement = trim((string)($announcementMessage ?? '')); ?>
    <?php if ($dashboardAnnouncement !== ''): ?>
        <div class="mb-6 rounded-xl border border-amber-700 bg-amber-950/60 px-5 py-4">
            <p class="text-sm font-semibold text-amber-300 mb-1">Announcement</p>
            <p class="text-sm text-amber-200 whitespace-pre-wrap break-words"><?= htmlspecialchars($dashboardAnnouncement, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    <?php endif; ?>

    <section class="mb-8">
    <h2 class="text-xl font-semibold text-zinc-300 mb-4">Friends</h2>

    <?php
        $toastMessage = '';
        $toastKind = 'error';
        $flashError = flash_get('error');
        $flashSuccess = flash_get('success');
        if ($flashError === 'invalid_chat') {
            $toastMessage = 'That chat was not found.';
        } elseif ($flashError === 'user_not_found') {
            $toastMessage = 'That user was not found.';
        } elseif ($flashSuccess === 'update_complete') {
            $toastMessage = 'Database updated successfully.';
            $toastKind = 'success';
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
        <section>
            <?php
                $incomingRequests = $pendingIncoming ?? [];
                $outgoingRequests = $pendingOutgoing ?? [];
                $hasRequests = !empty($incomingRequests) || !empty($outgoingRequests);
            ?>
            <?php if (!$hasRequests): ?>
                <p class="text-zinc-400 text-sm">No pending requests.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach ($incomingRequests as $request): ?>
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
                                        <div class="text-[11px] text-emerald-400 mt-0.5">Incoming request</div>
                                    </div>
                                </div>
                                <button class="bg-emerald-600 hover:bg-emerald-500 text-sm px-3 py-1.5 rounded-lg" onclick="acceptFriendRequest(<?= (int)$request->requester_id ?>)">Accept</button>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach ($outgoingRequests as $request): ?>
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
                                        <div class="text-[11px] text-zinc-400 mt-0.5">Outgoing request</div>
                                    </div>
                                </div>
                                <button class="bg-zinc-700 hover:bg-zinc-600 text-sm px-3 py-1.5 rounded-lg" onclick="cancelFriendRequest(<?= (int)($request->target_user_id ?? 0) ?>)">Cancel</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section>
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
    </section>

    <div class="grid grid-cols-1 2xl:grid-cols-2 gap-3 items-start">
    <div class="min-w-8">

    <section class="mb-6">
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

    <?php if (($invitesEnabled ?? false) === true): ?>
        <?php $inviteProfileUrl = base_url('/u/' . User::formatUserNumber((string)Auth::user()->user_number)); ?>
        <section class="mt-8">
            <h2 class="text-xl font-semibold text-zinc-300 mb-2">Invites</h2>
            <p class="text-zinc-400 text-sm mb-4 leading-relaxed">
                Invite your friends, family, or teammates to join you on this Prologue Server. Each user will need an invite code to create an account. If they don't receive the verification email after creating an account using the invite code you provided them, remind them to check their spam folder. Once their account is set up, they can use the Search function to add you as a friend. Enjoy!
            </p>

            <a href="<?= htmlspecialchars(base_url('/settings'), ENT_QUOTES, 'UTF-8') ?>"
            class="inline-flex items-center gap-2 text-sm text-emerald-400 hover:text-emerald-300 transition">
                Manage Invites <i class="fa fa-arrow-right text-xs"></i>
            </a>
        </section>
    <?php endif; ?>

    </div><!-- end left column -->
    <div class="min-w-0"><!-- right column -->

    <section class="mt-0">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h2 class="text-xl font-semibold text-zinc-300">Posts</h2>
            <div class="flex items-center gap-3">
                <button type="button" onclick="openNewPostModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm transition">
                    <i class="fa fa-plus text-xs"></i> New Post
                </button>
            </div>
        </div>

        <?php
            $recentPosts = $recentFriendPosts ?? [];
            $dashboardCurrentUser = Auth::user();
            $dashboardCurrentUserId = (int)($dashboardCurrentUser->id ?? 0);
        ?>
        <?php if (empty($recentPosts)): ?>
            <p class="text-zinc-400 text-sm">No posts from friends yet.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($recentPosts as $post): ?>
                    <?php
                        $postId = (int)($post->id ?? 0);
                        $postContent = trim((string)($post->content ?? ''));
                        if ($postId <= 0 || $postContent === '') {
                            continue;
                        }
                        $postOwnerId = (int)($post->user_id ?? 0);
                        $isOwnPost = $postOwnerId > 0 && $dashboardCurrentUserId > 0 && $postOwnerId === $dashboardCurrentUserId;
                        $postContainerClass = $isOwnPost ? 'bg-zinc-900' : 'bg-zinc-800';
                        $postAuthorUsername = htmlspecialchars((string)($post->username ?? ''), ENT_QUOTES, 'UTF-8');
                        $postAuthorNumber = (string)($post->user_number ?? '');
                        $postAuthorUrl = base_url('/u/' . User::formatUserNumber($postAuthorNumber));
                        $postCreatedAtRaw = trim((string)($post->created_at ?? ''));
                        $postCreatedAtTs = $postCreatedAtRaw !== '' ? strtotime($postCreatedAtRaw) : false;
                        $postCreatedAtLabel = $postCreatedAtTs !== false ? date('M j, Y H:i', $postCreatedAtTs) : 'Unknown';
                        $postDestinationUrl = base_url('/posts?post=' . $postId);
                        $postAvatar = User::avatarUrl($post);
                    ?>
                    <article
                        class="<?= htmlspecialchars($postContainerClass, ENT_QUOTES, 'UTF-8') ?> rounded-xl p-3 cursor-pointer hover:bg-zinc-700 transition"
                        data-dashboard-post-url="<?= htmlspecialchars($postDestinationUrl, ENT_QUOTES, 'UTF-8') ?>"
                        role="link"
                        tabindex="0"
                        title="Open this post"
                    >
                        <div class="flex items-center gap-2 mb-2">
                            <?php if ($postAvatar): ?>
                                <img src="<?= htmlspecialchars($postAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $postAuthorUsername ?> avatar" class="w-7 h-7 rounded-full object-cover border border-zinc-700">
                            <?php else: ?>
                                <div class="w-7 h-7 rounded-full border border-zinc-700 flex items-center justify-center font-semibold text-xs <?= htmlspecialchars(User::avatarColorClasses($postAuthorNumber), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(User::avatarInitial((string)($post->username ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($postAuthorUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-zinc-200 hover:text-white transition" onclick="event.stopPropagation()"><?= $postAuthorUsername ?></a>
                            <span class="text-xs text-zinc-500 ml-auto" data-utc="<?= htmlspecialchars($postCreatedAtRaw, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($postCreatedAtRaw, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($postCreatedAtLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <p class="text-sm text-zinc-100 whitespace-pre-wrap break-words leading-6"><?= nl2br(htmlspecialchars($postContent, ENT_QUOTES, 'UTF-8')) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="mt-4">
                <a href="<?= htmlspecialchars(base_url('/posts'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 text-sm text-emerald-400 hover:text-emerald-300 transition">
                    All Posts <i class="fa fa-arrow-right text-xs"></i>
                </a>
            </div>
        <?php endif; ?>
    </section>

    </div><!-- end right column -->
    </div><!-- end two-column grid -->
</div>