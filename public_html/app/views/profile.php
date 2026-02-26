<?php
$postReactionCodes = Post::REACTION_CODES;
$currentUser = Auth::user();
$isCurrentUserAdmin = strtolower((string)($currentUser->role ?? '')) === 'admin';
$postReactionCodeToLabel = [
    '1F44D' => 'Like',
    '1F44E' => 'Dislike',
    '2665' => 'Love',
    '1F923' => 'Laugh',
    '1F622' => 'Cry',
    '1F436' => 'Pup',
    '1F4A9' => 'Poop'
];

$unicodeCharForPostReaction = static function (string $reactionCode): string {
    $hex = strtoupper(trim($reactionCode));
    if ($hex === '' || !ctype_xdigit($hex)) {
        return '';
    }
    $codepoint = hexdec($hex);
    if ($codepoint <= 0) {
        return '';
    }
    return mb_chr($codepoint, 'UTF-8');
};

$renderPostReactionBadges = static function (int $postId, array $reactions) use ($postReactionCodes, $postReactionCodeToLabel, $unicodeCharForPostReaction): string {
    if (count($reactions) === 0) {
        return '';
    }

    $reactionByCode = [];
    foreach ($reactions as $reaction) {
        $code = Post::normalizeReactionCode($reaction->reaction_code ?? '');
        if ($code === '') {
            continue;
        }
        $reactionByCode[$code] = $reaction;
    }

    $badges = '';
    foreach ($postReactionCodes as $code) {
        if (!isset($reactionByCode[$code])) {
            continue;
        }

        $reaction = $reactionByCode[$code];
        $count = max(0, (int)($reaction->count ?? 0));
        if ($count <= 0) {
            continue;
        }

        $users = [];
        if (isset($reaction->users) && is_array($reaction->users)) {
            foreach ($reaction->users as $username) {
                $normalizedUsername = User::normalizeUsername($username ?? '');
                if ($normalizedUsername !== '') {
                    $users[] = $normalizedUsername;
                }
            }
        }

        $label = $postReactionCodeToLabel[$code] ?? 'Reaction';
        $tooltip = $label . ': ' . (count($users) > 0 ? implode(', ', $users) : 'No users');
        $reactedByCurrentUser = !empty($reaction->reacted_by_current_user);
        $badgeClass = $reactedByCurrentUser
            ? 'bg-zinc-700 border-zinc-500 text-zinc-100'
            : 'bg-zinc-800 border-zinc-700 text-zinc-300 hover:bg-zinc-700';

        $emojiChar = $unicodeCharForPostReaction($code);
        $emojiMarkup = $emojiChar !== ''
            ? '<span class="text-lg leading-none">' . htmlspecialchars($emojiChar, ENT_QUOTES, 'UTF-8') . '</span>'
            : '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';

        $badges .= '<button type="button" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-[12px] js-profile-post-reaction-badge ' . $badgeClass . '" data-post-id="' . (int)$postId . '" data-reaction-code="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '">'
            . $emojiMarkup
            . '<span>' . $count . '</span>'
            . '</button>';
    }

    if ($badges === '') {
        return '';
    }

    return '<div class="flex items-center gap-1.5">' . $badges . '</div>';
};

$renderPostReactionPicker = static function (int $postId) use ($postReactionCodes, $postReactionCodeToLabel, $unicodeCharForPostReaction): string {
    $options = '';
    foreach ($postReactionCodes as $code) {
        $label = $postReactionCodeToLabel[$code] ?? 'Reaction';
        $emojiChar = $unicodeCharForPostReaction($code);
        $emojiMarkup = $emojiChar !== ''
            ? '<span class="text-2xl leading-none">' . htmlspecialchars($emojiChar, ENT_QUOTES, 'UTF-8') . '</span>'
            : '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';

        $options .= '<button type="button" class="w-10 h-10 rounded-full hover:bg-zinc-800 flex items-center justify-center js-profile-post-reaction-option" data-post-id="' . (int)$postId . '" data-reaction-code="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">' . $emojiMarkup . '</button>';
    }

    return '<div class="hidden js-profile-post-reaction-picker absolute left-0 bottom-full mb-1.5 z-30" data-post-reaction-picker-for="' . (int)$postId . '"><div class="inline-flex items-center gap-1.5 bg-zinc-900 border border-zinc-700 rounded-full px-2 py-1">' . $options . '</div></div>';
};
?>

<div class="h-full overflow-auto">
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
            $lastActiveLabel = $lastActiveTs !== false ? date('M j, Y H:i', $lastActiveTs) : 'Never';

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

<div class="px-8 pb-8" id="profile-posts-root" data-profile-user-id="<?= (int)$profile->id ?>" data-can-react-posts="<?= !empty($canReactToPosts) ? '1' : '0' ?>">
    <div class="flex items-center justify-between gap-3 mb-4">
        <h2 class="text-xl font-semibold text-zinc-100">Posts</h2>
        <div class="flex items-center gap-3">
            <span class="text-xs text-zinc-500 uppercase tracking-wide"><?= (int)($totalPosts ?? count($posts ?? [])) ?> total</span>
            <?php if ((int)$profile->id === (int)$currentUserId): ?>
                <button type="button" onclick="openNewPostModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm transition">
                    <i class="fa fa-plus text-xs"></i> New Post
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="profile-post-list" class="space-y-3">
        <?php foreach (($posts ?? []) as $post): ?>
            <?php
                $postId = (int)($post->id ?? 0);
                $postContent = trim((string)($post->content ?? ''));
                if ($postId <= 0 || $postContent === '') {
                    continue;
                }

                $postCreatedAtRaw = trim((string)($post->created_at ?? ''));
                $postCreatedAtTs = $postCreatedAtRaw !== '' ? strtotime($postCreatedAtRaw) : false;
                $postCreatedAtLabel = $postCreatedAtTs !== false ? date('M j, Y H:i', $postCreatedAtTs) : 'Unknown';
                $postReactions = (isset($post->reactions) && is_array($post->reactions)) ? $post->reactions : [];
                $postOwnerId = (int)($post->user_id ?? (int)$profile->id);
                $canDeletePost = Post::canUserDeletePost($currentUser, $post);
            ?>
            <article class="bg-zinc-800 rounded-xl p-3" data-profile-post-id="<?= $postId ?>">
                <div class="text-zinc-100 whitespace-pre-wrap break-words leading-6"><?= nl2br(htmlspecialchars($postContent, ENT_QUOTES, 'UTF-8')) ?></div>
                <div class="relative mt-2">
                    <?= $renderPostReactionPicker($postId) ?>
                    <div class="text-xs flex items-center gap-3">
                        <span class="text-zinc-500" data-utc="<?= htmlspecialchars($postCreatedAtRaw, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($postCreatedAtRaw, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($postCreatedAtLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($canDeletePost): ?>
                            <button
                                type="button"
                                class="text-zinc-400 hover:text-red-300 js-profile-post-delete-open"
                                data-post-id="<?= $postId ?>"
                                data-post-username="<?= htmlspecialchars((string)$profile->username, ENT_QUOTES, 'UTF-8') ?>"
                                aria-label="Delete post"
                            >
                                Delete
                            </button>
                        <?php endif; ?>
                        <?php if (!empty($canReactToPosts)): ?>
                            <button type="button" class="text-zinc-400 hover:text-zinc-300 js-profile-post-react-link" data-post-id="<?= $postId ?>">React</button>
                        <?php endif; ?>
                        <?= $renderPostReactionBadges($postId, $postReactions) ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if (count($posts ?? []) === 0): ?>
            <p class="text-sm text-zinc-400">No posts yet.</p>
        <?php endif; ?>
    </div>

    <?php
        $currentPage = (int)($currentPage ?? 1);
        $totalPages = (int)($totalPages ?? 1);
        $profilePageBaseUrl = base_url('/u/' . htmlspecialchars(User::formatUserNumber($profile->user_number), ENT_QUOTES, 'UTF-8'));
    ?>
    <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between mt-6 pt-4 border-t border-zinc-700">
            <?php if ($currentPage > 1): ?>
                <a href="<?= $profilePageBaseUrl ?>?page=<?= $currentPage - 1 ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800 border border-zinc-700 text-zinc-300 hover:bg-zinc-700 text-sm transition"><i class="fa fa-arrow-left text-xs"></i> Previous</a>
            <?php else: ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800/40 border border-zinc-700/40 text-zinc-600 text-sm cursor-default"><i class="fa fa-arrow-left text-xs"></i> Previous</span>
            <?php endif; ?>
            <span class="text-zinc-400 text-sm">Page <?= $currentPage ?> of <?= $totalPages ?></span>
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= $profilePageBaseUrl ?>?page=<?= $currentPage + 1 ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800 border border-zinc-700 text-zinc-300 hover:bg-zinc-700 text-sm transition">Next <i class="fa fa-arrow-right text-xs"></i></a>
            <?php else: ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800/40 border border-zinc-700/40 text-zinc-600 text-sm cursor-default">Next <i class="fa fa-arrow-right text-xs"></i></span>
            <?php endif; ?>
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