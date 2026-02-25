<?php
$postReactionCodes = Post::REACTION_CODES;
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

$renderPostReactionPicker = static function (int $postId, int $postOwnerId) use ($postReactionCodes, $postReactionCodeToLabel, $unicodeCharForPostReaction): string {
    $options = '';
    foreach ($postReactionCodes as $code) {
        $label = $postReactionCodeToLabel[$code] ?? 'Reaction';
        $emojiChar = $unicodeCharForPostReaction($code);
        $emojiMarkup = $emojiChar !== ''
            ? '<span class="text-2xl leading-none">' . htmlspecialchars($emojiChar, ENT_QUOTES, 'UTF-8') . '</span>'
            : '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';

        $options .= '<button type="button" class="w-10 h-10 rounded-full hover:bg-zinc-800 flex items-center justify-center js-profile-post-reaction-option" data-post-id="' . (int)$postId . '" data-reaction-code="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">' . $emojiMarkup . '</button>';
    }

    return '<div class="hidden js-profile-post-reaction-picker absolute left-0 bottom-full mb-1.5 z-30" data-post-reaction-picker-for="' . (int)$postId . '" data-post-owner-id="' . (int)$postOwnerId . '"><div class="inline-flex items-center gap-1.5 bg-zinc-900 border border-zinc-700 rounded-full px-2 py-1">' . $options . '</div></div>';
};
?>

<div class="p-8 overflow-auto" id="profile-posts-root" data-can-react-posts="1">
    <?php
        $selectedPostScope = ($selectedPostScope ?? 'friends') === 'server' ? 'server' : 'friends';
        $posts = $posts ?? ($friendPosts ?? []);
        $currentUser = Auth::user();
        $currentUserId = (int)($currentUser->id ?? 0);
        $isCurrentUserAdmin = strtolower((string)($currentUser->role ?? '')) === 'admin';
        $friendsScopeUrl = base_url('/posts?scope=friends');
        $serverScopeUrl = base_url('/posts?scope=server');
    ?>
    <div class="mb-6 flex items-center gap-4">
        <a href="<?= htmlspecialchars(base_url('/'), ENT_QUOTES, 'UTF-8') ?>" class="text-zinc-400 hover:text-zinc-200 transition">
            <i class="fa fa-arrow-left"></i>
        </a>
        <h1 class="text-3xl font-bold">Posts</h1>
        <div class="ml-2 inline-flex items-center rounded-lg border border-zinc-700 p-1 text-xs">
            <a
                href="<?= htmlspecialchars($friendsScopeUrl, ENT_QUOTES, 'UTF-8') ?>"
                class="px-2.5 py-1 rounded-md transition <?= $selectedPostScope === 'friends' ? 'bg-zinc-700 text-zinc-100' : 'text-zinc-400 hover:text-zinc-200' ?>"
            >
                Friends
            </a>
            <a
                href="<?= htmlspecialchars($serverScopeUrl, ENT_QUOTES, 'UTF-8') ?>"
                class="px-2.5 py-1 rounded-md transition <?= $selectedPostScope === 'server' ? 'bg-zinc-700 text-zinc-100' : 'text-zinc-400 hover:text-zinc-200' ?>"
            >
                Server
            </a>
        </div>
        <button type="button" onclick="openNewPostModal()" class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm transition">
            <i class="fa fa-plus text-xs"></i> New Post
        </button>
    </div>

    <?php
        $currentPage = (int)($currentPage ?? 1);
        $totalPages = (int)($totalPages ?? 1);
        $totalPosts = (int)($totalPosts ?? 0);
        $scopeParam = 'scope=' . urlencode($selectedPostScope);
    ?>
    <?php if (empty($posts)): ?>
        <p class="text-zinc-400 text-sm"><?= $selectedPostScope === 'server' ? 'No posts on this server yet.' : 'No posts from friends yet.' ?></p>
    <?php else: ?>
        <div class="space-y-4" id="friends-post-list">
            <?php foreach ($posts as $post): ?>
                <?php
                    $postId = (int)($post->id ?? 0);
                    $postContent = trim((string)($post->content ?? ''));
                    if ($postId <= 0 || $postContent === '') {
                        continue;
                    }
                    $postOwnerId = (int)($post->user_id ?? 0);
                    $postAuthorUsername = (string)($post->username ?? '');
                    $postAuthorNumber = (string)($post->user_number ?? '');
                    $postAuthorUrl = base_url('/u/' . User::formatUserNumber($postAuthorNumber));
                    $postCreatedAtRaw = trim((string)($post->created_at ?? ''));
                    $postCreatedAtTs = $postCreatedAtRaw !== '' ? strtotime($postCreatedAtRaw) : false;
                    $postCreatedAtLabel = $postCreatedAtTs !== false ? date('Y-m-d H:i', $postCreatedAtTs) : 'Unknown';
                    $postReactions = (isset($post->reactions) && is_array($post->reactions)) ? $post->reactions : [];
                    $postAvatar = User::avatarUrl($post);
                    $canDeletePost = Post::canUserDeletePost($currentUser, $post);
                    $isOwnPost = $postOwnerId > 0 && $currentUserId > 0 && $postOwnerId === $currentUserId;
                    $postContainerClass = $isOwnPost ? 'bg-zinc-900' : 'bg-zinc-800';
                ?>
                <article class="<?= htmlspecialchars($postContainerClass, ENT_QUOTES, 'UTF-8') ?> rounded-xl p-4" data-profile-post-id="<?= $postId ?>">
                    <div class="flex items-center gap-2.5 mb-3">
                        <a href="<?= htmlspecialchars($postAuthorUrl, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($postAvatar): ?>
                                <img src="<?= htmlspecialchars($postAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($postAuthorUsername, ENT_QUOTES, 'UTF-8') ?> avatar" class="w-9 h-9 rounded-full object-cover border border-zinc-700">
                            <?php else: ?>
                                <div class="w-9 h-9 rounded-full border border-zinc-700 flex items-center justify-center font-semibold text-sm <?= htmlspecialchars(User::avatarColorClasses($postAuthorNumber), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(User::avatarInitial($postAuthorUsername), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                        </a>
                        <div class="min-w-0">
                            <a href="<?= htmlspecialchars($postAuthorUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-medium text-zinc-100 hover:text-white transition truncate block"><?= htmlspecialchars($postAuthorUsername, ENT_QUOTES, 'UTF-8') ?></a>
                            <span class="text-xs text-zinc-500" data-utc="<?= htmlspecialchars($postCreatedAtRaw, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($postCreatedAtRaw, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($postCreatedAtLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                    <div class="text-zinc-100 whitespace-pre-wrap break-words leading-6"><?= nl2br(htmlspecialchars($postContent, ENT_QUOTES, 'UTF-8')) ?></div>
                    <div class="relative mt-3">
                        <?= $renderPostReactionPicker($postId, $postOwnerId) ?>
                        <div class="text-xs flex items-center gap-3">
                            <?php if ($canDeletePost): ?>
                                <button
                                    type="button"
                                    class="text-zinc-400 hover:text-red-300 js-profile-post-delete-open"
                                    data-post-id="<?= $postId ?>"
                                    data-post-username="<?= htmlspecialchars($postAuthorUsername, ENT_QUOTES, 'UTF-8') ?>"
                                    aria-label="Delete post"
                                >
                                    Delete
                                </button>
                            <?php endif; ?>
                            <button type="button" class="text-zinc-400 hover:text-zinc-300 js-profile-post-react-link" data-post-id="<?= $postId ?>">React</button>
                            <?= $renderPostReactionBadges($postId, $postReactions) ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-between mt-6 pt-4 border-t border-zinc-700">
                <?php if ($currentPage > 1): ?>
                    <a href="<?= htmlspecialchars(base_url('/posts?' . $scopeParam . '&page=' . ($currentPage - 1)), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800 border border-zinc-700 text-zinc-300 hover:bg-zinc-700 text-sm transition"><i class="fa fa-arrow-left text-xs"></i> Previous</a>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800/40 border border-zinc-700/40 text-zinc-600 text-sm cursor-default"><i class="fa fa-arrow-left text-xs"></i> Previous</span>
                <?php endif; ?>
                <span class="text-zinc-400 text-sm">Page <?= $currentPage ?> of <?= $totalPages ?></span>
                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?= htmlspecialchars(base_url('/posts?' . $scopeParam . '&page=' . ($currentPage + 1)), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800 border border-zinc-700 text-zinc-300 hover:bg-zinc-700 text-sm transition">Next <i class="fa fa-arrow-right text-xs"></i></a>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800/40 border border-zinc-700/40 text-zinc-600 text-sm cursor-default">Next <i class="fa fa-arrow-right text-xs"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
