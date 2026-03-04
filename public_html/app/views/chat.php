<?php
$emojiDirs = [];
if (defined('STORAGE_FILESYSTEM_ROOT')) {
    $configuredStorageRoot = trim((string)STORAGE_FILESYSTEM_ROOT);
    if ($configuredStorageRoot !== '') {
        $emojiDirs[] = rtrim($configuredStorageRoot, '/') . '/emojis';
    }
}
$emojiDirs[] = dirname(__DIR__, 3) . '/storage/emojis';
$emojiDirs[] = dirname(__DIR__, 2) . '/assets/emojis';
$emojiDirs = array_values(array_unique($emojiDirs));

$emojiDir = '';
$emojiFiles = [];
foreach ($emojiDirs as $candidateDir) {
    $candidateFiles = glob(rtrim($candidateDir, '/') . '/*.svg') ?: [];
    if (count($candidateFiles) > 0) {
        $emojiDir = $candidateDir;
        $emojiFiles = $candidateFiles;
        break;
    }
}

if ($emojiDir === '' && count($emojiDirs) > 0) {
    $emojiDir = $emojiDirs[0];
}

$emojiFileNames = array_map(static fn($path) => basename($path), $emojiFiles);
sort($emojiFileNames, SORT_STRING);

$emojiFileKeys = [];
foreach ($emojiFileNames as $emojiFileName) {
    $emojiFileKeys[strtoupper((string)preg_replace('/\.svg$/i', '', $emojiFileName))] = true;
}

$attachmentAcceptedTypes = strtolower((string)(Setting::get('attachments_accepted_file_types') ?? 'png,jpg'));
$attachmentMaxSizeMb = (int)round(Attachment::maxFileSizeBytes() / (1024 * 1024));
$attachmentAcceptedExtensions = Attachment::acceptedExtensions();
$attachmentsEnabled = count($attachmentAcceptedExtensions) > 0;
$attachmentFileInputAccept = implode(',', array_map(fn($ext) => '.' . $ext, $attachmentAcceptedExtensions));

$isGroupChat = (($chat->type ?? 'personal') === 'group');
$isPersonalChat = !$isGroupChat;
$isGroupMember = (bool)($isGroupMember ?? true);
$isReadOnlyPublicViewer = (bool)($isReadOnlyPublicViewer ?? false);
$isRequestablePreview = (bool)($isRequestablePreview ?? false);
$groupVisibility = strtolower((string)($groupVisibility ?? 'none'));
$currentUserJoinRequestStatus = strtolower((string)($currentUserJoinRequestStatus ?? ''));
$canApproveJoinRequests = (bool)($canApproveJoinRequests ?? false);
$pendingGroupJoinRequests = is_array($pendingGroupJoinRequests ?? null) ? $pendingGroupJoinRequests : [];
$groupMemberCount = (int)($groupMemberCount ?? 0);
$groupMessageCount = (int)($groupMessageCount ?? 0);
$canSendMessages = (bool)($canSendMessages ?? true);
$messageRestrictionReason = (string)($messageRestrictionReason ?? '');
$canStartCalls = (bool)($canStartCalls ?? true);
$messageDisabledNoticeText = 'Messaging is currently disabled.';
if ($messageRestrictionReason === 'banned_user') {
    $messageDisabledNoticeText = "You can't send messages to a banned user.";
} elseif ($messageRestrictionReason === 'not_friends') {
    $messageDisabledNoticeText = 'Messaging is disabled in this private chat until you add each other as friends again.';
} elseif ($messageRestrictionReason === 'group_muted') {
    $messageDisabledNoticeText = 'You are muted in this group.';
} elseif ($messageRestrictionReason === 'group_members_only') {
    $messageDisabledNoticeText = 'Join this group to send messages.';
} elseif ($messageRestrictionReason === 'group_read_only_public') {
    $messageDisabledNoticeText = 'This is a public read-only view. Join the group to participate.';
}
$canReportChat = ((int)$chat->created_by !== (int)$currentUserId);
$isGroupOwner = $isGroupChat && ((int)$chat->created_by === (int)$currentUserId);
$isCurrentUserAdmin = (bool)($isCurrentUserAdmin ?? false);
$canTakeGroupOwnership = $isGroupChat && !$isGroupOwner && $isCurrentUserAdmin;
$isGroupModerator = false;
if ($isGroupChat && $isGroupMember) {
    foreach (($members ?? []) as $member) {
        if ((int)($member->id ?? 0) !== (int)$currentUserId) {
            continue;
        }
        $isGroupModerator = strtolower((string)($member->group_role ?? 'member')) === 'moderator';
        break;
    }
}
$canManageGroupSettings = $isGroupChat && ($isGroupOwner || $isCurrentUserAdmin);
$canModerateMembers = $isGroupChat && $isGroupMember && ($isGroupOwner || $isGroupModerator || $isCurrentUserAdmin);
$canAddUsers = $isGroupChat && $canManageGroupSettings && $isGroupMember;
$hasChatActions = ($isGroupChat && ($isGroupMember || $isCurrentUserAdmin)) || $canReportChat;
$pinnedMessage = $pinnedMessage ?? null;
$personalChatUserId = 0;
$personalChatUserNumber = '';
$personalChatStatusDotClass = null;
$personalChatStatusLabel = null;
$personalChatPeerUsername = '';
$currentUserUsername = '';

if ($isPersonalChat) {
    foreach (($members ?? []) as $member) {
        if ((int)($member->id ?? 0) === (int)$currentUserId) {
            $currentUserUsername = (string)($member->username ?? '');
            continue;
        }

        $personalChatUserId = (int)($member->id ?? 0);
        $personalChatUserNumber = preg_replace('/\D+/', '', (string)($member->user_number ?? ''));
        $personalChatStatusDotClass = (string)($member->effective_status_dot_class ?? 'bg-zinc-500');
        $personalChatStatusLabel = (string)($member->effective_status_label ?? 'Offline');
        $personalChatPeerUsername = (string)($member->username ?? '');
        break;
    }
}

if ($currentUserUsername === '') {
    foreach (($members ?? []) as $member) {
        if ((int)($member->id ?? 0) === (int)$currentUserId) {
            $currentUserUsername = (string)($member->username ?? '');
            break;
        }
    }
}

$reactionCodes = Message::REACTION_CODES;
$reactionCodeToLabel = [
    '1F44D' => 'Like',
    '1F44E' => 'Dislike',
    '2665' => 'Love',
    '1F923' => 'Laugh',
    '1F622' => 'Cry',
    '1F436' => 'Pup',
    '1F4A9' => 'Poop'
];

$resolveEmojiFilenameByCode = static function (string $reactionCode) use ($emojiFileKeys): string {
    $key = strtoupper(trim($reactionCode));
    if ($key === '') {
        return '';
    }

    if (isset($emojiFileKeys[$key])) {
        return $key . '.svg';
    }
    if (!str_contains($key, 'FE0F') && isset($emojiFileKeys[$key . '-FE0F'])) {
        return $key . '-FE0F.svg';
    }

    $withoutFe0f = preg_replace('/(?:-)?FE0F/i', '', $key);
    if ($withoutFe0f !== null && $withoutFe0f !== '' && isset($emojiFileKeys[$withoutFe0f])) {
        return $withoutFe0f . '.svg';
    }

    return '';
};

$unicodeCharForCode = static function (string $reactionCode): string {
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

$renderStoredMentionsToPlain = static function (string $content, $mentionMap): string {
    $map = [];
    if (is_object($mentionMap)) {
        $map = (array)$mentionMap;
    } elseif (is_array($mentionMap)) {
        $map = $mentionMap;
    }

    return preg_replace_callback('/@\[(\d{16})\|([a-z][a-z0-9]{3,31})\]/i', static function ($matches) use ($map) {
        $userNumber = (string)($matches[1] ?? '');
        $fallbackUsername = strtolower((string)($matches[2] ?? ''));
        $username = strtolower((string)($map[$userNumber] ?? $fallbackUsername));
        return '@' . $username;
    }, $content) ?? $content;
};
?>

    <div class="flex-1 flex flex-col h-full" id="chat-view" data-chat-id="<?= (int)$chat->id ?>" data-chat-number="<?= htmlspecialchars($chat->chat_number, ENT_QUOTES, 'UTF-8') ?>" data-chat-type="<?= htmlspecialchars($chat->type ?? 'personal', ENT_QUOTES, 'UTF-8') ?>" data-chat-owner-id="<?= (int)$chat->created_by ?>" data-current-user-id="<?= (int)$currentUserId ?>" data-first-unseen-message-id="<?= (int)($firstUnseenMessageId ?? 0) ?>" data-personal-user-id="<?= (int)$personalChatUserId ?>" data-can-send-messages="<?= $canSendMessages ? '1' : '0' ?>" data-message-restriction-reason="<?= htmlspecialchars($messageRestrictionReason, ENT_QUOTES, 'UTF-8') ?>" data-can-start-calls="<?= $canStartCalls ? '1' : '0' ?>" data-user-in-active-call="<?= !empty($isUserInActiveCall) ? '1' : '0' ?>" data-current-username="<?= htmlspecialchars($currentUserUsername, ENT_QUOTES, 'UTF-8') ?>" data-peer-username="<?= htmlspecialchars($personalChatPeerUsername, ENT_QUOTES, 'UTF-8') ?>" data-is-admin="<?= $isCurrentUserAdmin ? '1' : '0' ?>" data-group-edit-window="<?= htmlspecialchars($groupEditWindow ?? 'never', ENT_QUOTES, 'UTF-8') ?>" data-group-delete-window="<?= htmlspecialchars($groupDeleteWindow ?? 'never', ENT_QUOTES, 'UTF-8') ?>" data-group-visibility="<?= htmlspecialchars($groupVisibility, ENT_QUOTES, 'UTF-8') ?>" data-is-group-member="<?= $isGroupMember ? '1' : '0' ?>" data-is-group-moderator="<?= $isGroupModerator ? '1' : '0' ?>">
    <div class="h-16 border-b border-zinc-800 flex items-center px-6 justify-between">
        <div class="flex items-center gap-4 relative">
            <div class="flex items-center gap-2">
                <?php if ($isPersonalChat && $personalChatUserNumber !== ''): ?>
                    <a href="<?= htmlspecialchars(base_url('/u/' . User::formatUserNumber($personalChatUserNumber)), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-zinc-100 hover:text-zinc-100 hover:underline underline-offset-2">
                        <span id="chat-title"><?= htmlspecialchars($chatTitle ?? ('Chat #' . User::formatUserNumber($chat->chat_number)), ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php else: ?>
                    <span id="chat-title" class="font-semibold"><?= htmlspecialchars($chatTitle ?? ('Chat #' . User::formatUserNumber($chat->chat_number)), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($isPersonalChat && $personalChatStatusLabel !== null): ?>
                    <span id="chat-title-status-dot" class="inline-block w-2 h-2 rounded-full <?= htmlspecialchars($personalChatStatusDotClass ?? 'bg-zinc-500', ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($personalChatStatusLabel ?? 'Offline', ENT_QUOTES, 'UTF-8') ?>"></span>
                <?php endif; ?>
            </div>
            <?php if ($hasChatActions): ?>
            <button type="button" id="chat-header-menu-toggle" class="w-8 h-8 rounded-lg bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 text-zinc-300" aria-label="Open chat actions menu" aria-expanded="false" aria-controls="chat-header-menu">
                <i class="fa-solid fa-ellipsis-vertical"></i>
            </button>
            <div id="chat-header-menu" class="hidden absolute top-full left-0 mt-2 min-w-44 bg-zinc-900 border border-zinc-700 rounded-xl shadow-2xl p-1 z-30">
                <?php if ($isGroupChat): ?>
                <?php if ($canAddUsers): ?>
                <button type="button" data-chat-action="add-user" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-zinc-800">Add User</button>
                <?php endif; ?>
                <?php if ($canManageGroupSettings): ?>
                <button type="button" data-chat-action="rename-chat" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-zinc-800">Rename Chat</button>
                <?php endif; ?>
                <?php if ($canManageGroupSettings): ?>
                <button type="button" data-chat-action="group-settings" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-zinc-800">Group Settings</button>
                <?php endif; ?>
                <?php if ($canTakeGroupOwnership): ?>
                <button type="button" data-chat-action="take-ownership" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-zinc-800">Take Ownership</button>
                <?php endif; ?>
                <?php if ($isGroupMember): ?>
                <button type="button" data-chat-action="leave-group" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-zinc-800 text-red-300">Leave Group</button>
                <?php endif; ?>
                <?php if ($canManageGroupSettings): ?>
                <button type="button" data-chat-action="delete-group" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-zinc-800 text-red-300">Delete Group</button>
                <?php endif; ?>
                <?php endif; ?>
                <?php if ($canReportChat): ?>
                <button type="button" data-chat-action="report-chat" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-zinc-800 text-red-300">Report Chat</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="flex gap-6">
            <button id="start-voice-call-button" onclick="startVoiceCall()" class="prologue-accent hover:text-emerald-300 <?= $canStartCalls ? '' : 'opacity-50 cursor-not-allowed' ?> <?= !empty($isUserInActiveCall) ? 'hidden' : '' ?>" <?= $canStartCalls ? '' : 'disabled title="You can\'t call a banned user"' ?>><i class="fa fa-phone text-2xl"></i></button>
        </div>
    </div>
    <?php if ($isGroupChat && $isRequestablePreview): ?>
    <div class="px-6 py-4 border-b border-zinc-800">
        <div class="bg-zinc-900 border border-zinc-700 rounded-2xl p-4">
            <div class="text-sm text-zinc-300">This group is requestable.</div>
            <div class="mt-2 text-sm text-zinc-400">Members: <?= (int)$groupMemberCount ?> · Messages: <?= (int)$groupMessageCount ?></div>
            <div class="mt-4 flex items-center gap-3">
                <?php if ($currentUserJoinRequestStatus === 'pending'): ?>
                    <button type="button" id="cancel-group-join-request" class="px-4 py-2 rounded-xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-100">Cancel Request</button>
                    <span class="text-xs text-zinc-500">Request pending approval</span>
                <?php else: ?>
                    <button type="button" id="request-group-join" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white">Request Access</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($isGroupChat && $isReadOnlyPublicViewer): ?>
    <div class="px-6 py-3 border-b border-zinc-800 text-sm text-zinc-400">
        Public read-only view. Join the group to post messages and interact.
        <div class="mt-2 flex items-center gap-3">
            <?php if ($currentUserJoinRequestStatus === 'pending'): ?>
                <button type="button" id="cancel-group-join-request" class="px-3 py-1.5 rounded-lg bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-100 text-xs">Cancel Request</button>
            <?php else: ?>
                <button type="button" id="request-group-join" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs">Request to Join</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($isGroupChat && $canApproveJoinRequests && count($pendingGroupJoinRequests) > 0): ?>
    <div class="px-6 py-3 border-b border-zinc-800 text-sm">
        <div class="text-xs uppercase tracking-wide text-zinc-400 mb-2">Pending Join Requests</div>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($pendingGroupJoinRequests as $pendingJoin): ?>
                <span class="inline-flex items-center gap-2 bg-zinc-800 border border-zinc-700 rounded-full px-3 py-1" data-join-request-user-id="<?= (int)$pendingJoin->requester_user_id ?>">
                    <span class="text-zinc-200"><?= htmlspecialchars((string)$pendingJoin->username, ENT_QUOTES, 'UTF-8') ?></span>
                    <button type="button" class="text-emerald-300 hover:text-emerald-200" onclick="approveGroupJoinRequest(<?= (int)$pendingJoin->requester_user_id ?>)">Approve</button>
                    <button type="button" class="text-red-300 hover:text-red-200" onclick="denyGroupJoinRequest(<?= (int)$pendingJoin->requester_user_id ?>)">Deny</button>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($isGroupChat && ($isGroupMember || $isReadOnlyPublicViewer)): ?>
    <?php $otherMembersCount = count(array_filter($members ?? [], fn($m) => (int)$m->id !== (int)$currentUserId)); ?>
    <?php
        $groupMembersOrdered = [];
        $groupMembers = is_array($members ?? null) ? $members : [];
        $selfMember = null;
        foreach ($groupMembers as $groupMember) {
            if ((int)($groupMember->id ?? 0) === (int)$currentUserId) {
                $selfMember = $groupMember;
                continue;
            }
            $groupMembersOrdered[] = $groupMember;
        }
        if ($selfMember) {
            array_unshift($groupMembersOrdered, $selfMember);
        }
    ?>
    <div class="px-6 py-3 border-b border-zinc-800 text-sm">
        <div class="text-xs uppercase tracking-wide text-zinc-400 mb-2">Users in Group</div>
        <div class="flex flex-wrap items-center gap-2">
            <?php foreach ($groupMembersOrdered as $member): ?>
                <?php $memberIsSelf = (int)($member->id ?? 0) === (int)$currentUserId; ?>
                <?php $memberIsInCall = (int)($member->is_in_group_call ?? 0) === 1; ?>
                <?php $friendLabel = $memberIsSelf ? 'You' : (((int)($member->is_friend ?? 0) === 1) ? 'Friend' : 'Not Friend'); ?>
                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 border <?= $memberIsSelf ? 'bg-zinc-700 border-zinc-600' : 'bg-zinc-800 border-zinc-700' ?> <?= $memberIsInCall ? 'border-b-2 border-b-emerald-400' : '' ?>" data-group-member-user-id="<?= (int)$member->id ?>" data-group-member-in-call="<?= $memberIsInCall ? '1' : '0' ?>" data-group-member-username="<?= htmlspecialchars((string)$member->username, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="inline-block w-1.5 h-1.5 rounded-full <?= htmlspecialchars($member->effective_status_dot_class ?? 'bg-zinc-500', ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($member->effective_status_label ?? 'Offline', ENT_QUOTES, 'UTF-8') ?>"></span>
                    <a href="<?= htmlspecialchars(base_url('/u/' . User::formatUserNumber($member->user_number)), ENT_QUOTES, 'UTF-8') ?>" class="hover:underline underline-offset-2" title="<?= htmlspecialchars($friendLabel, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($friendLabel, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($member->username, ENT_QUOTES, 'UTF-8') ?></a>
                    <?php if ((int)$member->id === (int)$chat->created_by): ?>
                    <i class="fa-solid fa-crown prologue-accent" title="Group owner" aria-label="Group owner"></i>
                    <?php elseif (strtolower((string)($member->group_role ?? 'member')) === 'moderator'): ?>
                    <i class="fa-solid fa-shield-halved text-sky-300" title="Moderator" aria-label="Moderator"></i>
                    <?php endif; ?>
                    <?php if ((int)($member->is_group_muted ?? 0) === 1): ?>
                    <i class="fa-solid fa-volume-xmark text-amber-300" title="Muted" aria-label="Muted"></i>
                    <?php endif; ?>
                    <?php
                        $memberId = (int)$member->id;
                        $memberUsernameJson = json_encode((string)$member->username, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                        $memberIsSelf = $memberId === (int)$currentUserId;
                        $memberIsOwner = $memberId === (int)$chat->created_by;
                        $memberIsModerator = strtolower((string)($member->group_role ?? 'member')) === 'moderator';
                        $canRoleManageMember = $canManageGroupSettings && !$memberIsOwner && !$memberIsSelf;
                        $canMuteManageMember = $canModerateMembers && !$memberIsOwner && !$memberIsModerator && !$memberIsSelf;
                        $canRemoveMember = $canModerateMembers && !$memberIsOwner && !$memberIsSelf;
                        $hasMemberManageActions = $canRoleManageMember || $canMuteManageMember || $canRemoveMember;
                    ?>
                    <?php if ($hasMemberManageActions): ?>
                    <span class="relative inline-flex items-center">
                        <button
                            type="button"
                            class="inline-flex items-center text-zinc-300 hover:text-zinc-100"
                            title="User actions"
                            aria-label="User actions"
                            aria-expanded="false"
                            aria-controls="group-member-menu-<?= $memberId ?>"
                            data-group-member-menu-toggle
                            data-menu-id="group-member-menu-<?= $memberId ?>"
                        >
                            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                        </button>
                        <div id="group-member-menu-<?= $memberId ?>" data-group-member-menu class="hidden absolute right-0 top-full mt-2 min-w-36 bg-zinc-900 border border-zinc-700 rounded-xl shadow-2xl p-1.5 z-30">
                            <?php if ($canRoleManageMember): ?>
                                <?php if ($memberIsModerator): ?>
                                <button type="button" data-group-member-action class="w-full text-left px-2.5 py-2 rounded-lg hover:bg-zinc-800 text-sm text-sky-300" onclick='demoteGroupModerator(<?= $memberId ?>, <?= $memberUsernameJson ?>)'>
                                    <i class="fa-solid fa-user-minus mr-2" aria-hidden="true"></i>Demote
                                </button>
                                <?php else: ?>
                                <button type="button" data-group-member-action class="w-full text-left px-2.5 py-2 rounded-lg hover:bg-zinc-800 text-sm text-sky-300" onclick='promoteGroupModerator(<?= $memberId ?>, <?= $memberUsernameJson ?>)'>
                                    <i class="fa-solid fa-user-shield mr-2" aria-hidden="true"></i>Promote
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($canMuteManageMember): ?>
                                <?php if ((int)($member->is_group_muted ?? 0) === 1): ?>
                                <button type="button" data-group-member-action class="w-full text-left px-2.5 py-2 rounded-lg hover:bg-zinc-800 text-sm text-amber-300" onclick='unmuteGroupMember(<?= $memberId ?>, <?= $memberUsernameJson ?>)'>
                                    <i class="fa-solid fa-volume-high mr-2" aria-hidden="true"></i>Unmute
                                </button>
                                <?php else: ?>
                                <button type="button" data-group-member-action class="w-full text-left px-2.5 py-2 rounded-lg hover:bg-zinc-800 text-sm text-amber-300" onclick='muteGroupMember(<?= $memberId ?>, <?= $memberUsernameJson ?>)'>
                                    <i class="fa-solid fa-volume-xmark mr-2" aria-hidden="true"></i>Mute
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($canRemoveMember): ?>
                                <button type="button" data-group-member-action class="w-full text-left px-2.5 py-2 rounded-lg hover:bg-zinc-800 text-sm text-red-300" onclick='removeGroupMember(<?= $memberId ?>, <?= $memberUsernameJson ?>)'>
                                    <i class="fa-solid fa-trash mr-2" aria-hidden="true"></i>Delete
                                </button>
                            <?php endif; ?>
                        </div>
                    </span>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
            <?php if ($otherMembersCount === 0 && $canAddUsers): ?>
                <button type="button" onclick="openAddUserModal()" class="inline-flex items-center gap-1.5 bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 rounded-full px-3 py-1 text-zinc-300 hover:text-zinc-100">
                    <i class="fa-solid fa-plus text-xs"></i> Add User
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!$isRequestablePreview): ?>
    <?php
        $initialPinnedMessageId = (int)($pinnedMessage->id ?? 0);
        $initialPinnedMessageUsername = (string)($pinnedMessage->username ?? 'Unknown user');
        $initialPinnedMessageContent = (string)($pinnedMessage->content ?? '');
        $initialPinnedMessageCreatedAt = (string)($pinnedMessage->created_at ?? '');
        $initialPinnedMessageMentionMap = json_encode((object)($pinnedMessage->mention_map ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    ?>
    <div id="pinned-message-banner" class="<?= $initialPinnedMessageId > 0 ? '' : 'hidden' ?> px-6 py-3 border-b border-zinc-800 bg-zinc-900/70 transition-all duration-200 ease-out" data-pinned-message-id="<?= $initialPinnedMessageId ?>">
        <div class="flex items-start justify-between gap-4 group">
            <div class="min-w-0">
                <div class="text-xs uppercase tracking-wide text-zinc-400">Pinned message</div>
                <div class="mt-1 text-sm text-zinc-300">
                    <span id="pinned-message-username" class="font-semibold"><?= htmlspecialchars($initialPinnedMessageUsername, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="text-zinc-500">·</span>
                    <span id="pinned-message-time" class="text-zinc-500"><?= htmlspecialchars(preg_replace('/:(\d{2})(?!.*:\d{2})/', '', $initialPinnedMessageCreatedAt), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div id="pinned-message-content" class="text-sm text-zinc-200 leading-6 line-clamp-2" data-raw-content="<?= htmlspecialchars($initialPinnedMessageContent, ENT_QUOTES, 'UTF-8') ?>" data-mention-map="<?= htmlspecialchars($initialPinnedMessageMentionMap, ENT_QUOTES, 'UTF-8') ?>">
                    <?= nl2br(htmlspecialchars($renderStoredMentionsToPlain($initialPinnedMessageContent, $pinnedMessage->mention_map ?? []), ENT_QUOTES, 'UTF-8')) ?>
                </div>
            </div>
            <div class="flex items-center gap-4 md:opacity-0 md:group-hover:opacity-100 md:pointer-events-none md:group-hover:pointer-events-auto md:transition-opacity md:duration-150 md:ease-out">
                <button type="button" id="pinned-message-goto" class="inline-flex items-center gap-1.5 text-sm text-zinc-400 hover:text-zinc-200" title="Go to message" aria-label="Go to message">
                    <i class="fa-solid fa-highlighter" aria-hidden="true"></i>
                    <span class="hidden md:inline">Go to Message</span>
                </button>
                <button type="button" id="pinned-message-unpin" class="inline-flex items-center gap-1.5 text-sm text-zinc-400 hover:text-zinc-200" title="Unpin" aria-label="Unpin">
                    <i class="fa-solid fa-thumbtack-slash" aria-hidden="true"></i>
                    <span class="hidden md:inline">Unpin</span>
                </button>
            </div>
        </div>
    </div>
    <div id="messages" class="flex-1 p-6 overflow-y-auto">
        <?php
            $previousUserId = null;
            $previousWasSystemEvent = false;
            $clusterStarts = [];
            $prevClusterUserId = null;
            foreach ($messages as $index => $messageForCluster) {
                if ($messageForCluster->is_system_event ?? false) {
                    $clusterStarts[$index] = false;
                    $prevClusterUserId = null;
                    continue;
                }
                $clusterStarts[$index] = ($prevClusterUserId === null) || ($prevClusterUserId !== (int)$messageForCluster->user_id);
                $prevClusterUserId = (int)$messageForCluster->user_id;
            }
            unset($prevClusterUserId);

            $latestClusterIndexByUser = [];
            for ($index = count($messages) - 1; $index >= 0; $index--) {
                if ($messages[$index]->is_system_event ?? false) {
                    continue;
                }
                if (!($clusterStarts[$index] ?? false)) {
                    continue;
                }

                $clusterUserId = (int)$messages[$index]->user_id;
                if (!isset($latestClusterIndexByUser[$clusterUserId])) {
                    $latestClusterIndexByUser[$clusterUserId] = $index;
                }
            }

            $buildSystemEventGroupKey = static function (int $chatId, array $runMessages): string {
                $firstMessage = $runMessages[0] ?? null;
                $lastMessage = $runMessages[count($runMessages) - 1] ?? null;
                $firstId = (int)($firstMessage->id ?? 0);
                $lastId = (int)($lastMessage->id ?? 0);
                $firstTimestamp = (string)($firstMessage->created_at ?? '');
                $lastTimestamp = (string)($lastMessage->created_at ?? '');
                $rawFingerprint = $chatId . '|' . $firstId . '|' . $lastId . '|' . count($runMessages) . '|' . $firstTimestamp . '|' . $lastTimestamp;
                $hash = 7;
                $length = strlen($rawFingerprint);
                for ($charIndex = 0; $charIndex < $length; $charIndex++) {
                    $hash = (($hash * 31) + ord($rawFingerprint[$charIndex])) & 0xFFFFFFFF;
                }

                return $chatId . '-' . $firstId . '-' . $lastId . '-' . count($runMessages) . '-' . dechex($hash);
            };

            $displayEntries = [];
            $messageCount = count($messages);
            $messageIndex = 0;
            while ($messageIndex < $messageCount) {
                $candidate = $messages[$messageIndex];
                if (!($candidate->is_system_event ?? false)) {
                    $displayEntries[] = [
                        'type' => 'message',
                        'message' => $candidate,
                        'message_index' => $messageIndex,
                    ];
                    $messageIndex++;
                    continue;
                }

                $runStart = $messageIndex;
                while ($messageIndex < $messageCount && ($messages[$messageIndex]->is_system_event ?? false)) {
                    $messageIndex++;
                }

                $runMessages = array_slice($messages, $runStart, $messageIndex - $runStart);
                if (count($runMessages) <= 2) {
                    foreach ($runMessages as $runOffset => $runMessage) {
                        $displayEntries[] = [
                            'type' => 'system_message',
                            'message' => $runMessage,
                            'message_index' => $runStart + $runOffset,
                            'group_key' => '',
                            'is_collapsible_middle' => false,
                            'hidden_by_default' => false,
                        ];
                    }
                    continue;
                }

                $groupKey = $buildSystemEventGroupKey((int)($chat->id ?? 0), $runMessages);
                $hiddenCount = max(0, count($runMessages) - 2);

                $displayEntries[] = [
                    'type' => 'system_message',
                    'message' => $runMessages[0],
                    'message_index' => $runStart,
                    'group_key' => $groupKey,
                    'is_collapsible_middle' => false,
                    'hidden_by_default' => false,
                ];

                $displayEntries[] = [
                    'type' => 'system_toggle',
                    'group_key' => $groupKey,
                    'hidden_count' => $hiddenCount,
                ];

                for ($middleIndex = 1; $middleIndex < count($runMessages) - 1; $middleIndex++) {
                    $displayEntries[] = [
                        'type' => 'system_message',
                        'message' => $runMessages[$middleIndex],
                        'message_index' => $runStart + $middleIndex,
                        'group_key' => $groupKey,
                        'is_collapsible_middle' => true,
                        'hidden_by_default' => true,
                    ];
                }

                $displayEntries[] = [
                    'type' => 'system_message',
                    'message' => $runMessages[count($runMessages) - 1],
                    'message_index' => $messageIndex - 1,
                    'group_key' => $groupKey,
                    'is_collapsible_middle' => false,
                    'hidden_by_default' => false,
                ];
            }
        ?>
        <?php foreach ($displayEntries as $entry): ?>
            <?php if (($entry['type'] ?? '') === 'system_toggle'): ?>
                <?php
                    $systemToggleGroupKey = (string)($entry['group_key'] ?? '');
                    $systemToggleHiddenCount = max(0, (int)($entry['hidden_count'] ?? 0));
                    $systemToggleLabel = 'Show ' . $systemToggleHiddenCount . ' more ' . ($systemToggleHiddenCount === 1 ? 'event' : 'events');
                ?>
                <div class="flex gap-3 mt-1">
                    <div class="w-10 shrink-0"><div class="w-10 h-1"></div></div>
                    <div class="min-w-0 flex-1">
                        <button
                            type="button"
                            class="js-system-events-toggle text-xs text-zinc-500 hover:text-zinc-300"
                            data-system-event-group-key="<?= htmlspecialchars($systemToggleGroupKey, ENT_QUOTES, 'UTF-8') ?>"
                            data-system-event-hidden-count="<?= $systemToggleHiddenCount ?>"
                            aria-expanded="false"
                        >
                            <span class="js-system-events-toggle-label"><?= htmlspecialchars($systemToggleLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                    </div>
                </div>
                <?php $previousWasSystemEvent = true; $previousUserId = null; continue; ?>
            <?php endif; ?>

            <?php if (($entry['type'] ?? '') === 'system_message'): ?>
                <?php
                    $message = $entry['message'];
                    $systemEntryGroupKey = (string)($entry['group_key'] ?? '');
                    $isSystemEntryCollapsibleMiddle = !empty($entry['is_collapsible_middle']);
                    $isSystemEntryHiddenByDefault = !empty($entry['hidden_by_default']);
                    $systemEntryExtraAttributes = $isSystemEntryCollapsibleMiddle
                        ? ' data-system-event-group-key="' . htmlspecialchars($systemEntryGroupKey, ENT_QUOTES, 'UTF-8') . '" data-system-event-hidden="1"'
                        : '';
                ?>
                <?php
                    $isNewPrologueCluster = !$previousWasSystemEvent;
                    $systemEventType = (string)($message->event_type ?? '');
                    $isCallSystemEvent = strpos($systemEventType, 'call_') === 0;
                    $sysFullTimestamp = (string)($message->created_at ?? '');
                    $sysCompactTimestamp = preg_replace('/:(\d{2})(?!.*:\d{2})/', '', $sysFullTimestamp);
                    $systemContentRaw = (string)($message->content ?? '');
                    $systemContentEscaped = htmlspecialchars($systemContentRaw, ENT_QUOTES, 'UTF-8');
                    $systemContentHtml = preg_replace_callback(
                        '#/c/\d{4}-\d{4}-\d{4}-\d{4}/delete#',
                        static function ($matches) {
                            $path = (string)($matches[0] ?? '');
                            return '<a href="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '" class="text-red-400 hover:text-red-300 hover:underline underline-offset-2">Delete chat</a>';
                        },
                        $systemContentEscaped
                    );
                    if (!is_string($systemContentHtml) || $systemContentHtml === '') {
                        $systemContentHtml = $systemContentEscaped;
                    }
                ?>
                <div class="flex gap-3 <?= $isNewPrologueCluster ? 'mt-4' : 'mt-1' ?><?= $isSystemEntryHiddenByDefault ? ' hidden' : '' ?>"<?= $systemEntryExtraAttributes ?>>
                    <div class="w-10 shrink-0">
                        <?php if ($isNewPrologueCluster): ?>
                            <div class="w-10 h-10 rounded-full border border-zinc-700 bg-zinc-900 text-zinc-400 flex items-center justify-center font-semibold mt-0.5"><i class="fa-solid fa-comments text-xs" aria-hidden="true"></i></div>
                        <?php else: ?>
                            <div class="w-10 h-10"></div>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <?php if ($isNewPrologueCluster): ?>
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="text-sm font-semibold leading-5 text-zinc-400">Prologue</span>
                            </div>
                        <?php endif; ?>
                        <div class="text-zinc-400 text-[15px] leading-6"><?= $systemContentHtml ?></div>
                        <div class="relative mt-0.5">
                            <div class="text-xs flex items-center gap-3">
                                <span class="text-zinc-500" data-utc="<?= htmlspecialchars($sysFullTimestamp, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($sysFullTimestamp, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($sysCompactTimestamp, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php $previousWasSystemEvent = true; $previousUserId = null; continue; ?>
            <?php endif; ?>
            <?php $message = $entry['message']; $index = (int)($entry['message_index'] ?? -1); ?>
            <?php
                $avatarUrl = $message->avatar_url ?? User::avatarUrl($message);
                $isNewGroup = ($previousUserId === null) || ((int)$previousUserId !== (int)$message->user_id);
                $showStatus = $isNewGroup
                    && $isGroupChat
                    && ((int)$message->user_id !== (int)$currentUserId)
                    && (($latestClusterIndexByUser[(int)$message->user_id] ?? -1) === $index);
                $fullTimestamp = (string)($message->created_at ?? '');
                $compactTimestamp = preg_replace('/:(\d{2})(?!.*:\d{2})/', '', $fullTimestamp);
                $quotedContent = (string)($message->quoted_content ?? '');
                $quotedUsername = (string)($message->quoted_username ?? '');
                $quotedUserNumber = (string)($message->quoted_user_number ?? '');
                $quotedMentionMap = $message->quote_mention_map ?? (object)[];
                $quotedDisplayContent = $renderStoredMentionsToPlain($quotedContent, $quotedMentionMap);
            ?>
            <div class="flex gap-3 <?= $isNewGroup ? 'mt-4' : 'mt-1' ?> group" data-message-id="<?= (int)$message->id ?>" data-message-user-id="<?= (int)$message->user_id ?>" data-message-created-at="<?= htmlspecialchars((string)$message->created_at, ENT_QUOTES, 'UTF-8') ?>" data-message-is-quoted="<?= !empty($message->is_quoted) ? '1' : '0' ?>" data-message-has-attachments="<?= !empty($message->has_attachments) ? '1' : '0' ?>" data-message-edited-at="<?= htmlspecialchars((string)($message->edited_at ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <div class="w-10 shrink-0">
                    <?php if ($isNewGroup): ?>
                        <?php if ($avatarUrl): ?>
                            <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($message->username, ENT_QUOTES, 'UTF-8') ?> avatar" class="w-10 h-10 rounded-full object-cover border border-zinc-700 mt-0.5">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-full border border-zinc-700 flex items-center justify-center font-semibold mt-0.5 <?= htmlspecialchars(User::avatarColorClasses($message->user_number), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(User::avatarInitial($message->username), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="w-10 h-10"></div>
                    <?php endif; ?>
                </div>
                <div class="min-w-0 flex-1">
                    <?php if ($isNewGroup): ?>
                        <div class="flex items-center gap-2 mb-0.5">
                            <a href="<?= htmlspecialchars(base_url('/u/' . User::formatUserNumber($message->user_number)), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold leading-5 inline-block prologue-accent hover:text-emerald-300 hover:underline underline-offset-2"><?= htmlspecialchars($message->username, ENT_QUOTES, 'UTF-8') ?></a>
                            <?php if ($showStatus): ?>
                                <span class="inline-block w-1.5 h-1.5 rounded-full <?= htmlspecialchars($message->effective_status_dot_class ?? 'bg-zinc-500', ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($message->effective_status_label ?? 'Offline', ENT_QUOTES, 'UTF-8') ?>"></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php $mentionMapJson = json_encode((object)($message->mention_map ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'; ?>
                    <?php if ($quotedContent !== ''): ?>
                        <div class="mt-1.5 mb-2 p-2 rounded-lg border border-zinc-700 bg-zinc-900/70 text-sm">
                            <div class="text-zinc-400 text-xs mb-1">
                                <?php if ($quotedUserNumber !== ''): ?>
                                    <a href="<?= htmlspecialchars(base_url('/u/' . User::formatUserNumber($quotedUserNumber)), ENT_QUOTES, 'UTF-8') ?>" class="hover:underline underline-offset-2"><?= htmlspecialchars($quotedUsername !== '' ? $quotedUsername : 'Unknown user', ENT_QUOTES, 'UTF-8') ?></a>
                                <?php else: ?>
                                    <?= htmlspecialchars($quotedUsername !== '' ? $quotedUsername : 'Unknown user', ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                            <div class="text-zinc-300 leading-5 truncate" title="<?= htmlspecialchars($quotedDisplayContent, ENT_QUOTES, 'UTF-8') ?>"><?= nl2br(htmlspecialchars($quotedDisplayContent, ENT_QUOTES, 'UTF-8')) ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="text-zinc-200 text-[17px] leading-6 js-message-content" data-raw-content="<?= htmlspecialchars($message->content, ENT_QUOTES, 'UTF-8') ?>" data-mention-map="<?= htmlspecialchars($mentionMapJson, ENT_QUOTES, 'UTF-8') ?>"><?= nl2br(htmlspecialchars($message->content, ENT_QUOTES, 'UTF-8')) ?></div>
                    <?php
                        $canDeleteAttachmentThisMessage = false;
                        if ((int)$message->user_id === (int)$currentUserId || $isCurrentUserAdmin) {
                            if ($isPersonalChat || $isCurrentUserAdmin) {
                                $canDeleteAttachmentThisMessage = true;
                            } else {
                                $dwAttachment = $groupDeleteWindow ?? 'never';
                                if ($dwAttachment === 'forever') {
                                    $canDeleteAttachmentThisMessage = true;
                                } elseif ($dwAttachment !== 'never' && (int)$dwAttachment > 0) {
                                    $canDeleteAttachmentThisMessage = (time() - strtotime($message->created_at)) <= (int)$dwAttachment;
                                }
                            }
                        }
                    ?>
                    <?php if (!empty($message->attachments) && is_array($message->attachments)): ?>
                        <div class="mt-3 flex flex-wrap gap-3">
                            <?php foreach ($message->attachments as $attachment): ?>
                                <?php
                                    $attachmentExt = (string)($attachment->file_extension ?? '');
                                    $attachmentCategory = Attachment::extensionCategory($attachmentExt);
                                    $attachmentUrl = htmlspecialchars($attachment->url ?? '', ENT_QUOTES, 'UTF-8');
                                    $attachmentName = htmlspecialchars($attachment->original_name ?? 'Attachment', ENT_QUOTES, 'UTF-8');
                                    $attachmentDownloadName = htmlspecialchars($attachment->original_name ?? (($attachment->file_name ?? '') . '.' . $attachmentExt), ENT_QUOTES, 'UTF-8');
                                    $attachmentIsDeleted = !empty($attachment->is_deleted);
                                    $attachmentDeleteReason = strtolower((string)($attachment->delete_reason ?? 'manual'));
                                    $attachmentDeleteLabel = $attachmentDeleteReason === 'expired' ? 'Expired attachment' : 'Deleted attachment';
                                    $attachmentSizeLabel = number_format(((int)$attachment->file_size) / 1024, 1) . ' KB';
                                    if ((int)$attachment->file_size >= 1024 * 1024) {
                                        $attachmentSizeLabel = number_format(((int)$attachment->file_size) / (1024 * 1024), 2) . ' MB';
                                    }
                                ?>
                                <div class="w-44 bg-zinc-800/70 border border-zinc-700 rounded-xl p-2">
                                    <?php if ($attachmentIsDeleted): ?>
                                        <div class="w-full h-24 rounded-lg border border-zinc-700 bg-zinc-900/70 flex flex-col items-center justify-center gap-1.5">
                                            <i class="fa-solid fa-file-circle-xmark text-2xl text-zinc-500"></i>
                                            <span class="text-xs text-zinc-400 text-center px-2"><?= htmlspecialchars($attachmentDeleteLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="mt-2 text-xs text-zinc-500 truncate" title="<?= $attachmentName ?>"><?= $attachmentName ?></div>
                                    <?php elseif ($attachmentCategory === 'image'): ?>
                                        <button
                                            type="button"
                                            class="js-lightbox-trigger block w-full"
                                            data-image-url="<?= $attachmentUrl ?>"
                                            data-image-title="<?= $attachmentName ?>"
                                        >
                                            <img
                                                src="<?= $attachmentUrl ?>"
                                                alt="<?= $attachmentName ?>"
                                                title="<?= $attachmentName ?>"
                                                class="w-full h-24 object-cover rounded-lg border border-zinc-700"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                        </button>
                                        <div class="mt-2 text-xs text-zinc-400 flex items-center justify-between gap-2">
                                            <span><?= htmlspecialchars($attachmentSizeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            <div class="flex items-center gap-2">
                                                <?php if ($canDeleteAttachmentThisMessage): ?>
                                                    <button type="button" class="text-red-300 hover:text-red-200 js-attachment-delete" data-attachment-id="<?= (int)$attachment->id ?>" title="Delete attachment" aria-label="Delete attachment">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <a href="<?= $attachmentUrl ?>" download="<?= $attachmentDownloadName ?>" class="text-zinc-300 hover:text-zinc-100" title="Download">
                                                    <i class="fa-solid fa-download"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <a href="<?= $attachmentUrl ?>" download="<?= $attachmentDownloadName ?>" class="block w-full">
                                            <div class="w-full h-24 rounded-lg border border-zinc-700 bg-zinc-800 flex flex-col items-center justify-center gap-1.5">
                                                <i class="fa-solid fa-file text-2xl text-zinc-400"></i>
                                                <span class="text-xs font-mono font-semibold text-zinc-300 uppercase">.<?= htmlspecialchars($attachmentExt, ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                        </a>
                                        <div class="mt-2 text-xs text-zinc-400 flex items-center justify-between gap-2">
                                            <span class="truncate" title="<?= $attachmentName ?>"><?= $attachmentName ?></span>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <?php if ($canDeleteAttachmentThisMessage): ?>
                                                    <button type="button" class="text-red-300 hover:text-red-200 js-attachment-delete" data-attachment-id="<?= (int)$attachment->id ?>" title="Delete attachment" aria-label="Delete attachment">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <a href="<?= $attachmentUrl ?>" download="<?= $attachmentDownloadName ?>" class="text-zinc-300 hover:text-zinc-100" title="Download">
                                                    <i class="fa-solid fa-download"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="relative mt-0.5">
                        <div class="hidden js-reaction-picker absolute left-0 bottom-full mb-1.5 z-30" data-reaction-picker-for="<?= (int)$message->id ?>">
                            <div class="inline-flex items-center gap-1.5 bg-zinc-900 border border-zinc-700 rounded-full px-2 py-1">
                                <?php foreach ($reactionCodes as $reactionCode): ?>
                                    <?php $emojiFilename = $resolveEmojiFilenameByCode($reactionCode); ?>
                                    <?php $emojiChar = $unicodeCharForCode($reactionCode); ?>
                                    <button type="button" class="w-10 h-10 rounded-full hover:bg-zinc-800 flex items-center justify-center js-reaction-option" data-reaction-message-id="<?= (int)$message->id ?>" data-reaction-code="<?= htmlspecialchars($reactionCode, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($reactionCodeToLabel[$reactionCode] ?? 'Reaction', ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if ($emojiFilename !== ''): ?>
                                            <img src="<?= htmlspecialchars(base_url('/emojis/' . $emojiFilename), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($emojiChar !== '' ? $emojiChar : ($reactionCodeToLabel[$reactionCode] ?? 'Reaction'), ENT_QUOTES, 'UTF-8') ?>" class="w-7 h-7" loading="lazy" decoding="async">
                                        <?php else: ?>
                                            <span><?= htmlspecialchars($emojiChar !== '' ? $emojiChar : ($reactionCodeToLabel[$reactionCode] ?? 'Reaction'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="text-xs flex items-center gap-3">
                            <span class="text-zinc-500" data-utc="<?= htmlspecialchars($fullTimestamp, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($fullTimestamp, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($compactTimestamp, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($message->edited_at)): ?>
                                <span class="text-zinc-500 italic">(edited)</span>
                            <?php endif; ?>
                            <?php if (!$isReadOnlyPublicViewer): ?>
                            <div class="flex items-center gap-3 md:opacity-0 md:group-hover:opacity-100 md:pointer-events-none md:group-hover:pointer-events-auto md:transition-opacity md:duration-150 md:ease-out">
                                <button type="button" class="text-zinc-400 hover:text-zinc-300 js-quote-link" title="Quote" aria-label="Quote" data-quote-message-id="<?= (int)$message->id ?>" data-quote-username="<?= htmlspecialchars((string)$message->username, ENT_QUOTES, 'UTF-8') ?>" data-quote-user-number="<?= htmlspecialchars((string)$message->user_number, ENT_QUOTES, 'UTF-8') ?>" data-quote-content="<?= htmlspecialchars((string)$message->content, ENT_QUOTES, 'UTF-8') ?>" data-quote-mention-map="<?= htmlspecialchars($mentionMapJson, ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-reply" aria-hidden="true"></i></button>
                                <?php if (empty($message->is_quoted)): ?>
                                    <?php
                                        $canEditThis = false;
                                        if ((int)$message->user_id === (int)$currentUserId || $isCurrentUserAdmin) {
                                            if ($isPersonalChat || $isCurrentUserAdmin) {
                                                $canEditThis = true;
                                            } else {
                                                $ew = $groupEditWindow ?? 'never';
                                                if ($ew === 'forever') { $canEditThis = true; }
                                                elseif ($ew !== 'never' && (int)$ew > 0) { $canEditThis = (time() - strtotime($message->created_at)) <= (int)$ew; }
                                            }
                                        }
                                    ?>
                                    <?php if ($canEditThis): ?>
                                    <button type="button" class="text-zinc-400 hover:text-zinc-300 js-edit-link" title="Edit" aria-label="Edit" data-edit-message-id="<?= (int)$message->id ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (empty($message->is_quoted)): ?>
                                    <?php
                                        $canDeleteThis = false;
                                        if ((int)$message->user_id === (int)$currentUserId || $isCurrentUserAdmin) {
                                            if ($isPersonalChat || $isCurrentUserAdmin) {
                                                $canDeleteThis = true;
                                            } else {
                                                $dw = $groupDeleteWindow ?? 'never';
                                                if ($dw === 'forever') { $canDeleteThis = true; }
                                                elseif ($dw !== 'never' && (int)$dw > 0) { $canDeleteThis = (time() - strtotime($message->created_at)) <= (int)$dw; }
                                            }
                                        }
                                    ?>
                                    <?php if ($canDeleteThis): ?>
                                    <button type="button" class="text-zinc-400 hover:text-zinc-300 js-delete-link" title="Delete" aria-label="Delete" data-delete-message-id="<?= (int)$message->id ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <button type="button" class="text-zinc-400 hover:text-zinc-300 js-pin-link" title="Pin" aria-label="Pin" data-pin-message-id="<?= (int)$message->id ?>"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i></button>
                                <button type="button" class="text-zinc-400 hover:text-zinc-300 js-react-link" title="React" aria-label="React" data-react-message-id="<?= (int)$message->id ?>"><i class="fa-solid fa-thumbs-up" aria-hidden="true"></i></button>
                            </div>
                            <?php endif; ?>
                            <?php $messageReactions = is_array($message->reactions ?? null) ? $message->reactions : []; ?>
                            <?php if (!empty($messageReactions)): ?>
                                <div class="flex items-center gap-1.5">
                                    <?php foreach ($messageReactions as $reaction): ?>
                                        <?php
                                            $reactionCode = strtoupper((string)($reaction->reaction_code ?? ''));
                                            $reactionCount = (int)($reaction->count ?? 0);
                                            $reactionUsers = is_array($reaction->users ?? null) ? $reaction->users : [];
                                            $reactionTitlePrefix = $reactionCodeToLabel[$reactionCode] ?? 'Reaction';
                                            $reactionTitleUsers = !empty($reactionUsers) ? implode(', ', $reactionUsers) : 'No users';
                                            $reactionTitle = $reactionTitlePrefix . ': ' . $reactionTitleUsers;
                                            $emojiFilename = $resolveEmojiFilenameByCode($reactionCode);
                                            $emojiChar = $unicodeCharForCode($reactionCode);
                                            $reactedByCurrentUser = !empty($reaction->reacted_by_current_user);
                                        ?>
                                        <?php if ($reactionCount > 0): ?>
                                            <button type="button" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-[12px] js-reaction-badge <?= $reactedByCurrentUser ? 'bg-zinc-700 border-zinc-500 text-zinc-100' : 'bg-zinc-800 border-zinc-700 text-zinc-300 hover:bg-zinc-700' ?>" data-reaction-message-id="<?= (int)$message->id ?>" data-reaction-code="<?= htmlspecialchars($reactionCode, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($reactionTitle, ENT_QUOTES, 'UTF-8') ?>">
                                                <?php if ($emojiFilename !== ''): ?>
                                                    <img src="<?= htmlspecialchars(base_url('/emojis/' . $emojiFilename), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($emojiChar !== '' ? $emojiChar : $reactionTitlePrefix, ENT_QUOTES, 'UTF-8') ?>" class="w-6 h-6" loading="lazy" decoding="async">
                                                <?php else: ?>
                                                    <span><?= htmlspecialchars($emojiChar !== '' ? $emojiChar : $reactionTitlePrefix, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                                <span><?= $reactionCount ?></span>
                                            </button>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php $previousUserId = (int)$message->user_id; $previousWasSystemEvent = false; ?>
        <?php endforeach; ?>
    </div>
    <div id="typing-indicator" class="px-6 pb-2 text-sm text-zinc-400 min-h-6 hidden" aria-live="polite"></div>
    <?php if (!$isReadOnlyPublicViewer): ?>
    <form id="message-form" class="p-6 border-t border-zinc-800 flex gap-4 relative">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" id="quoted-message-id" value="">
        <div id="quote-preview" class="hidden absolute left-6 right-6 bottom-[calc(100%+0.75rem)] bg-zinc-900 border border-zinc-700 rounded-2xl p-3 z-20">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs text-zinc-400">Replying to <span id="quote-preview-username" class="text-zinc-300"></span></div>
                    <div id="quote-preview-content" class="text-sm text-zinc-300 truncate"></div>
                </div>
                <button type="button" id="quote-preview-cancel" class="text-zinc-400 hover:text-zinc-300" aria-label="Clear quote">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <div id="message-composer-controls" class="w-full flex flex-wrap sm:flex-nowrap items-stretch gap-3 sm:gap-4 <?= $canSendMessages ? '' : 'hidden' ?>">
            <?php if ($attachmentsEnabled): ?>
            <button type="button" id="attachments-toggle" class="order-2 sm:order-1 w-[calc((100%-1.5rem)/3)] sm:w-14 rounded-3xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 min-h-12" aria-label="Open attachments menu" aria-expanded="false" aria-controls="attachments-drawer">
                <i class="fa-solid fa-paperclip"></i>
            </button>
            <?php endif; ?>
            <button type="button" id="emoji-toggle" class="order-2 sm:order-2 w-[calc((100%-1.5rem)/3)] sm:w-14 rounded-3xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 min-h-12" aria-label="Open emoji picker" aria-expanded="false" aria-controls="emoji-drawer">
                <i class="fa-regular fa-face-smile"></i>
            </button>
            <input type="text" id="message-input" maxlength="16384" class="order-1 sm:order-3 w-full sm:flex-1 bg-zinc-800 border border-zinc-700 rounded-3xl px-6 py-4" placeholder="Message..." required>
            <button type="submit" class="order-2 sm:order-4 w-[calc((100%-1.5rem)/3)] sm:w-auto bg-emerald-600 px-10 rounded-3xl min-h-12">Send</button>
        </div>
        <div id="message-disabled-notice" class="w-full bg-zinc-900 border border-zinc-700 rounded-2xl px-4 py-3 text-sm text-amber-300 <?= $canSendMessages ? 'hidden' : '' ?>"><?= htmlspecialchars($messageDisabledNoticeText, ENT_QUOTES, 'UTF-8') ?></div>
        <div id="emoji-drawer" class="hidden sm:absolute sm:left-6 sm:bottom-[calc(100%+0.75rem)] sm:w-[32rem] sm:max-w-[calc(100%-3rem)] sm:rounded-2xl sm:shadow-2xl fixed inset-0 sm:inset-auto z-50 sm:z-30 bg-zinc-900 sm:border sm:border-zinc-700 p-4 flex flex-col">
            <div class="flex items-center justify-between mb-3 sm:hidden">
                <h3 class="text-sm font-medium text-zinc-300">Emoji</h3>
                <button type="button" id="emoji-drawer-close" class="text-zinc-400 hover:text-zinc-200 w-10 h-10 flex items-center justify-center" aria-label="Close emoji picker">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="flex gap-3 items-start flex-1 min-h-0">
                <div class="flex-1 min-w-0 flex flex-col min-h-0">
                    <input type="text" id="emoji-search" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2 text-sm mb-3" placeholder="Search emoji (type char or code, e.g. 1f44d)">
                    <div id="emoji-grid" class="grid grid-cols-6 justify-start gap-3 sm:max-h-72 flex-1 overflow-y-auto pr-1"></div>
                </div>
                <div id="emoji-preview" class="hidden sm:flex w-20 flex-shrink-0 flex-col items-center gap-2 pt-10">
                    <img id="emoji-preview-img" src="" alt="Emoji preview" class="w-14 h-14 hidden">
                    <div id="emoji-preview-label" class="text-xs text-zinc-400 text-center leading-tight break-words w-full hidden"></div>
                </div>
            </div>
        </div>
        <?php if ($attachmentsEnabled): ?>
        <div id="attachments-drawer" class="hidden absolute left-6 right-6 bottom-[calc(100%+0.75rem)] bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-4 z-30">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div class="text-sm text-zinc-300">Attachments (<?= htmlspecialchars($attachmentAcceptedTypes, ENT_QUOTES, 'UTF-8') ?>, max <?= (int)$attachmentMaxSizeMb ?>MB)</div>
                <input type="file" id="attachments-file-input" class="hidden" accept="<?= htmlspecialchars($attachmentFileInputAccept, ENT_QUOTES, 'UTF-8') ?>" multiple>
                <button type="button" id="attachments-select-button" class="bg-zinc-800 hover:bg-zinc-700 border border-zinc-600 rounded-lg px-3 py-1.5 text-xs">Select files</button>
            </div>
            <div id="attachments-empty" class="text-sm text-zinc-400 border border-dashed border-zinc-700 rounded-xl px-4 py-6 text-center">No attachments yet. Click Select files.</div>
            <div id="attachments-list" class="hidden grid grid-cols-2 md:grid-cols-4 gap-3"></div>
        </div>
        <?php endif; ?>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
    window.PENDING_ATTACHMENTS = <?= json_encode($pendingAttachments ?? [], JSON_UNESCAPED_SLASHES) ?>;
    window.INITIAL_PINNED_MESSAGE = <?= json_encode($pinnedMessage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>

<div id="pin-replace-modal" class="hidden fixed inset-0 bg-black/70 z-50 p-4 md:p-6" aria-hidden="true">
    <div class="h-full w-full flex items-center justify-center">
        <div class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-6" role="dialog" aria-modal="true" aria-labelledby="pin-replace-modal-title">
            <h2 id="pin-replace-modal-title" class="text-lg font-semibold text-zinc-100">Replace pinned message?</h2>
            <p id="pin-replace-modal-description" class="mt-2 text-sm text-zinc-400">This chat already has a pinned message. Do you want to replace it?</p>
            <form id="pin-replace-form" class="mt-4">
                <div class="flex items-center justify-end gap-3">
                    <button type="button" id="pin-replace-cancel" class="px-4 py-2 rounded-xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-200">Cancel</button>
                    <button type="submit" id="pin-replace-submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white">Replace</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($isGroupChat): ?>
<div id="add-user-modal" class="hidden fixed inset-0 bg-black/70 z-50 p-4 md:p-6" aria-hidden="true">
    <div class="h-full w-full flex items-center justify-center">
        <div class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-6" role="dialog" aria-modal="true" aria-labelledby="add-user-modal-title">
            <h2 id="add-user-modal-title" class="text-lg font-semibold text-zinc-100">Add user to group</h2>
            <p class="mt-2 text-sm text-zinc-400">Enter the username you want to add to this group chat.</p>
            <form id="add-user-form" class="mt-4 space-y-4">
                <label for="add-user-input" class="block text-sm text-zinc-300">Username</label>
                <div class="relative">
                    <input type="text" id="add-user-input" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100" placeholder="Enter username" autocomplete="off" required>
                    <div id="add-user-typeahead" class="hidden absolute z-20 mt-2 w-full bg-zinc-900 border border-zinc-700 rounded-xl overflow-hidden"></div>
                </div>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" id="add-user-cancel" class="px-4 py-2 rounded-xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-200">Cancel</button>
                    <button type="submit" id="add-user-submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white">Add user</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="rename-chat-modal" class="hidden fixed inset-0 bg-black/70 z-50 p-4 md:p-6" aria-hidden="true">
    <div class="h-full w-full flex items-center justify-center">
        <div class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-6" role="dialog" aria-modal="true" aria-labelledby="rename-chat-modal-title">
            <h2 id="rename-chat-modal-title" class="text-lg font-semibold text-zinc-100">Would you like to name this chat?</h2>
            <form id="rename-chat-form" class="mt-4 space-y-4">
                <label for="rename-chat-input" class="block text-sm text-zinc-300">Chat name</label>
                <input type="text" id="rename-chat-input" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100" placeholder="Enter chat name">
                <div class="flex items-center justify-end gap-3">
                    <button type="button" id="rename-chat-cancel" class="px-4 py-2 rounded-xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-200">Cancel</button>
                    <button type="submit" id="rename-chat-submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="leave-group-modal" class="hidden fixed inset-0 bg-black/70 z-50 p-4 md:p-6" aria-hidden="true">
    <div class="h-full w-full flex items-center justify-center">
        <div class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-6" role="dialog" aria-modal="true" aria-labelledby="leave-group-modal-title">
            <h2 id="leave-group-modal-title" class="text-lg font-semibold text-zinc-100">Leave group</h2>
            <p class="mt-2 text-sm text-zinc-400" id="leave-group-modal-description">Are you sure you want to leave this group?</p>

            <form id="leave-group-form" class="mt-4 space-y-4">
                <div id="leave-group-owner-transfer" class="hidden">
                    <label for="leave-group-new-owner" class="block text-sm text-zinc-300 mb-1">Select new owner</label>
                    <select id="leave-group-new-owner" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100">
                        <option value="">Select member</option>
                    </select>
                    <p class="mt-2 text-xs text-zinc-500">You must transfer ownership before leaving because you created this group.</p>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" id="leave-group-cancel" class="px-4 py-2 rounded-xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-200">Cancel</button>
                    <button type="submit" id="leave-group-submit" class="px-4 py-2 rounded-xl bg-red-700 hover:bg-red-600 text-white">Leave Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="delete-group-modal" class="hidden fixed inset-0 bg-black/70 z-50 p-4 md:p-6" aria-hidden="true">
    <div class="h-full w-full flex items-center justify-center">
        <div class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-6" role="dialog" aria-modal="true" aria-labelledby="delete-group-modal-title">
            <h2 id="delete-group-modal-title" class="text-lg font-semibold text-zinc-100">Delete group</h2>
            <p class="mt-2 text-sm text-zinc-400">Are you sure you wish to delete this group chat?</p>
            <form id="delete-group-form" class="mt-4">
                <div class="flex items-center justify-end gap-3">
                    <button type="button" id="delete-group-cancel" class="px-4 py-2 rounded-xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-200">Cancel</button>
                    <button type="submit" id="delete-group-submit" class="px-4 py-2 rounded-xl bg-red-700 hover:bg-red-600 text-white">Delete Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="take-ownership-modal" class="hidden fixed inset-0 bg-black/70 z-50 p-4 md:p-6" aria-hidden="true">
    <div class="h-full w-full flex items-center justify-center">
        <div class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-6" role="dialog" aria-modal="true" aria-labelledby="take-ownership-modal-title">
            <h2 id="take-ownership-modal-title" class="text-lg font-semibold text-zinc-100">Take ownership</h2>
            <p class="mt-2 text-sm text-zinc-400">Are you sure you want to take ownership of this group?</p>
            <form id="take-ownership-form" class="mt-4">
                <div class="flex items-center justify-end gap-3">
                    <button type="button" id="take-ownership-cancel" class="px-4 py-2 rounded-xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-200">Cancel</button>
                    <button type="submit" id="take-ownership-submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white">Take Ownership</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($canManageGroupSettings): ?>
<div id="message-settings-modal" class="hidden fixed inset-0 bg-black/70 z-50 p-4 md:p-6" aria-hidden="true">
    <div class="h-full w-full flex items-center justify-center">
        <div class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-6" role="dialog" aria-modal="true" aria-labelledby="message-settings-modal-title">
            <h2 id="message-settings-modal-title" class="text-lg font-semibold text-zinc-100">Group Settings</h2>
            <p class="mt-2 text-sm text-zinc-400">Manage group access and message permissions.</p>
            <form id="message-settings-form" class="mt-4 space-y-4">
                <div class="text-xs uppercase tracking-wide text-zinc-400">Group</div>
                <div>
                    <label for="group-settings-visibility" class="block text-sm text-zinc-300 mb-1">Non-member Visibility</label>
                    <select id="group-settings-visibility" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100">
                        <option value="none">None</option>
                        <option value="requestable">Requestable</option>
                        <option value="public">Public</option>
                    </select>
                </div>
                <div class="text-xs uppercase tracking-wide text-zinc-400 pt-1">Messages</div>
                <div>
                    <label for="message-settings-edit-window" class="block text-sm text-zinc-300 mb-1">Edit Messages</label>
                    <select id="message-settings-edit-window" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100">
                        <option value="never">Never</option>
                        <option value="600">For 10 Minutes</option>
                        <option value="3600">For 1 Hour</option>
                        <option value="86400">For 1 Day</option>
                        <option value="forever">Forever</option>
                    </select>
                </div>
                <div>
                    <label for="message-settings-delete-window" class="block text-sm text-zinc-300 mb-1">Delete Messages</label>
                    <select id="message-settings-delete-window" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100">
                        <option value="never">Never</option>
                        <option value="600">For 10 Minutes</option>
                        <option value="3600">For 1 Hour</option>
                        <option value="86400">For 1 Day</option>
                        <option value="forever">Forever</option>
                    </select>
                </div>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" id="message-settings-cancel" class="px-4 py-2 rounded-xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-200">Cancel</button>
                    <button type="submit" id="message-settings-submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<div id="delete-message-modal" class="hidden fixed inset-0 bg-black/70 z-50 p-4 md:p-6" aria-hidden="true">
    <div class="h-full w-full flex items-center justify-center">
        <div class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-6" role="dialog" aria-modal="true" aria-labelledby="delete-message-modal-title">
            <h2 id="delete-message-modal-title" class="text-lg font-semibold text-zinc-100">Delete message</h2>
            <p class="mt-2 text-sm text-zinc-400">Are you sure you want to delete this message? This cannot be undone.</p>
            <form id="delete-message-form" class="mt-4">
                <input type="hidden" id="delete-message-id" value="">
                <div class="flex items-center justify-end gap-3">
                    <button type="button" id="delete-message-cancel" class="px-4 py-2 rounded-xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-200">Cancel</button>
                    <button type="submit" id="delete-message-submit" class="px-4 py-2 rounded-xl bg-red-700 hover:bg-red-600 text-white">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="attachment-lightbox" class="hidden fixed inset-0 bg-black/90 z-50 p-6">
    <button type="button" id="attachment-lightbox-close" class="absolute top-5 right-5 text-zinc-200 hover:text-white text-2xl" aria-label="Close image preview">
        <i class="fa-solid fa-xmark"></i>
    </button>
    <div class="h-full w-full flex items-center justify-center">
        <img id="attachment-lightbox-image" src="" alt="Attachment preview" class="max-h-full max-w-full rounded-xl border border-zinc-700">
    </div>
</div>