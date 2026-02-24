<div class="p-8">
    <div class="w-full">
        <?php $profileAvatar = User::avatarUrl($profile); ?>
        <div class="mb-5">
            <?php if ($profileAvatar): ?>
                <img src="<?= htmlspecialchars($profileAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($profile->username, ENT_QUOTES, 'UTF-8') ?> avatar" class="w-20 h-20 rounded-full object-cover border border-zinc-700">
            <?php else: ?>
                <div class="w-20 h-20 rounded-full border border-zinc-700 flex items-center justify-center text-2xl font-semibold <?= htmlspecialchars(User::avatarColorClasses($profile->user_number), ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars(User::avatarInitial($profile->username), ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
        </div>
        <h1 class="text-3xl font-bold mb-2"><?= htmlspecialchars($profile->username, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-xl text-zinc-400 mb-6"><?= htmlspecialchars(User::formatUserNumber($profile->user_number), ENT_QUOTES, 'UTF-8') ?></p>

        <?php
            $lastActiveRaw = trim((string)($profile->last_active_at ?? ''));
            $lastActiveTs = $lastActiveRaw !== '' ? strtotime($lastActiveRaw) : false;
            $lastActiveLabel = $lastActiveTs !== false ? date('Y-m-d H:i', $lastActiveTs) : 'Never';

            $joinedAtRaw = trim((string)($profile->created_at ?? ''));
            $joinedAtTs = $joinedAtRaw !== '' ? strtotime($joinedAtRaw) : false;
            $joinedAtLabel = $joinedAtTs !== false ? date('M j, Y', $joinedAtTs) : 'Unknown';
        ?>
        <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="rounded-xl border border-zinc-700 bg-zinc-800/60 p-3">
                <div class="text-xs uppercase tracking-wide text-zinc-500">Status</div>
                <div class="mt-1 text-sm <?= htmlspecialchars($profile->effective_status_text_class ?? 'text-zinc-500', ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($profile->effective_status_label ?? 'Offline', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <div class="rounded-xl border border-zinc-700 bg-zinc-800/60 p-3">
                <div class="text-xs uppercase tracking-wide text-zinc-500">Last Active</div>
                <div class="mt-1 text-sm text-zinc-200">
                    <?php if ($lastActiveRaw !== ''): ?>
                        <span data-utc="<?= htmlspecialchars($lastActiveRaw, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($lastActiveRaw, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lastActiveLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        Never
                    <?php endif; ?>
                </div>
            </div>
            <div class="rounded-xl border border-zinc-700 bg-zinc-800/60 p-3">
                <div class="text-xs uppercase tracking-wide text-zinc-500">Friends</div>
                <div class="mt-1 text-sm text-zinc-200"><?= (int)($friendCount ?? 0) ?></div>
            </div>
            <div class="rounded-xl border border-zinc-700 bg-zinc-800/60 p-3">
                <div class="text-xs uppercase tracking-wide text-zinc-500">Joined</div>
                <div class="mt-1 text-sm text-zinc-200"><?= htmlspecialchars($joinedAtLabel, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>

        <?php
            $profileActionBaseClass = 'inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-zinc-900';
            $profileActionMutedClass = $profileActionBaseClass . ' bg-zinc-800 border border-zinc-700 text-zinc-300 cursor-default';
            $profileActionNeutralClass = $profileActionBaseClass . ' bg-zinc-800 border border-zinc-700 text-zinc-100 hover:bg-zinc-700 focus:ring-zinc-500';
            $profileActionSuccessClass = $profileActionBaseClass . ' bg-emerald-600 text-white hover:bg-emerald-500 focus:ring-emerald-400';
            $profileActionWarnClass = $profileActionBaseClass . ' bg-amber-600 text-white hover:bg-amber-500 focus:ring-amber-400';
            $profileActionDangerClass = $profileActionBaseClass . ' bg-red-700 text-white hover:bg-red-600 focus:ring-red-400';
        ?>
        <?php if ((int)$profile->id !== (int)$currentUserId): ?>
        <div class="mt-1">
            <div class="text-xs uppercase tracking-wide text-zinc-500 mb-3">Actions</div>
            <div class="flex flex-wrap gap-2">
            <?php if (($friendshipStatus ?? null) === 'accepted'): ?>
            <?php if (!empty($personalChatNumber ?? null)): ?>
            <a href="<?= htmlspecialchars(base_url('/c/' . $personalChatNumber), ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($profileActionSuccessClass, ENT_QUOTES, 'UTF-8') ?>"><i class="fa fa-comment-dots text-xs"></i> Personal Chat</a>
            <?php endif; ?>
            <button type="button" onclick="toggleFavoriteUser(<?= (int)$profile->id ?>, <?= !empty($isFavorite ?? false) ? '0' : '1' ?>)" class="<?= htmlspecialchars(!empty($isFavorite ?? false) ? $profileActionNeutralClass : $profileActionWarnClass, ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa <?= !empty($isFavorite ?? false) ? 'fa-star-half-stroke' : 'fa-star' ?> text-xs"></i>
                <?= !empty($isFavorite ?? false) ? 'Remove Favorite' : 'Add to Favorites' ?>
            </button>
            <button type="button" onclick="openUnfriendModal(<?= (int)$profile->id ?>)" class="<?= htmlspecialchars($profileActionDangerClass, ENT_QUOTES, 'UTF-8') ?>"><i class="fa fa-user-minus text-xs"></i> Unfriend</button>
            <?php elseif (($friendshipStatus ?? null) === 'pending' && ($friendshipDirection ?? null) === 'incoming'): ?>
            <button onclick="acceptFriendRequest(<?= (int)$profile->id ?>)" class="<?= htmlspecialchars($profileActionSuccessClass, ENT_QUOTES, 'UTF-8') ?>"><i class="fa fa-check text-xs"></i> Accept Request</button>
            <?php elseif (($friendshipStatus ?? null) === 'pending' && ($friendshipDirection ?? null) === 'outgoing'): ?>
            <button type="button" class="<?= htmlspecialchars($profileActionMutedClass, ENT_QUOTES, 'UTF-8') ?>" disabled><i class="fa fa-paper-plane text-xs"></i> Request Sent</button>
            <?php else: ?>
            <button onclick="sendFriendRequestByValue('<?= htmlspecialchars(User::formatUserNumber($profile->user_number), ENT_QUOTES, 'UTF-8') ?>')" class="<?= htmlspecialchars($profileActionSuccessClass, ENT_QUOTES, 'UTF-8') ?>"><i class="fa fa-user-plus text-xs"></i> Add Friend</button>
            <?php endif; ?>
            <button onclick="reportTarget('user', <?= (int)$profile->id ?>)" class="<?= htmlspecialchars($profileActionDangerClass, ENT_QUOTES, 'UTF-8') ?>"><i class="fa fa-flag text-xs"></i> Report User</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ((int)$profile->id !== (int)$currentUserId && ($friendshipStatus ?? null) === 'accepted'): ?>
<div id="unfriend-confirm-modal" class="hidden fixed inset-0 bg-black/70 z-50 p-4 md:p-6" aria-hidden="true">
    <div class="h-full w-full flex items-center justify-center">
        <div class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-6" role="dialog" aria-modal="true" aria-labelledby="unfriend-confirm-title">
            <h2 id="unfriend-confirm-title" class="text-lg font-semibold text-zinc-100">Unfriend user</h2>
            <p class="mt-2 text-sm text-zinc-400">You will keep your existing conversation history, but you won’t be able to send messages in the private chat until you become friends again.</p>
            <div class="mt-5 flex items-center justify-end gap-3">
                <button type="button" id="unfriend-confirm-cancel" class="px-4 py-2 rounded-xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-200">Cancel</button>
                <button type="button" id="unfriend-confirm-submit" class="px-4 py-2 rounded-xl bg-red-700 hover:bg-red-600 text-white">Unfriend</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>