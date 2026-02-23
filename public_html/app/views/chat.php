<?php
$emojiDir = dirname(__DIR__, 2) . '/assets/emojis';
$emojiFiles = glob($emojiDir . '/*.svg') ?: [];
$emojiFileNames = array_map(static fn($path) => basename($path), $emojiFiles);
sort($emojiFileNames, SORT_STRING);

$emojiMetadata = [];
$emojiFileKeys = [];
foreach ($emojiFileNames as $emojiFileName) {
    $emojiFileKeys[strtoupper((string)preg_replace('/\.svg$/i', '', $emojiFileName))] = true;
}

$openMojiCsvPath = $emojiDir . '/openmoji.csv';
if (is_readable($openMojiCsvPath)) {
    $handle = fopen($openMojiCsvPath, 'rb');
    if ($handle !== false) {
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if (is_array($header)) {
            $headerIndex = array_flip($header);
            $hexIdx = $headerIndex['hexcode'] ?? null;
            $annotationIdx = $headerIndex['annotation'] ?? null;
            $tagsIdx = $headerIndex['tags'] ?? null;
            $openmojiTagsIdx = $headerIndex['openmoji_tags'] ?? null;
            $groupIdx = $headerIndex['group'] ?? null;
            $subgroupsIdx = $headerIndex['subgroups'] ?? null;

            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if ($hexIdx === null || !isset($row[$hexIdx])) {
                    continue;
                }

                $hex = strtoupper(trim((string)$row[$hexIdx]));
                if ($hex === '') {
                    continue;
                }

                $hexWithoutFe0f = preg_replace('/(?:-)?FE0F/i', '', $hex);
                $hasSvg = isset($emojiFileKeys[$hex]) || isset($emojiFileKeys[$hexWithoutFe0f]);
                if (!$hasSvg) {
                    continue;
                }

                $meta = [
                    'annotation' => ($annotationIdx !== null && isset($row[$annotationIdx])) ? trim((string)$row[$annotationIdx]) : '',
                    'tags' => ($tagsIdx !== null && isset($row[$tagsIdx])) ? trim((string)$row[$tagsIdx]) : '',
                    'openmoji_tags' => ($openmojiTagsIdx !== null && isset($row[$openmojiTagsIdx])) ? trim((string)$row[$openmojiTagsIdx]) : '',
                    'group' => ($groupIdx !== null && isset($row[$groupIdx])) ? trim((string)$row[$groupIdx]) : '',
                    'subgroups' => ($subgroupsIdx !== null && isset($row[$subgroupsIdx])) ? trim((string)$row[$subgroupsIdx]) : '',
                ];

                $emojiMetadata[$hex] = $meta;
                if ($hexWithoutFe0f !== '' && !isset($emojiMetadata[$hexWithoutFe0f])) {
                    $emojiMetadata[$hexWithoutFe0f] = $meta;
                }
            }
        }

        fclose($handle);
    }
}

$attachmentAcceptedTypes = strtolower((string)(Setting::get('attachments_accepted_file_types') ?? 'png,jpg'));
$attachmentMaxSizeMb = (int)round(Attachment::maxFileSizeBytes() / (1024 * 1024));
$attachmentAcceptedExtensions = Attachment::acceptedExtensions();
$attachmentsEnabled = count($attachmentAcceptedExtensions) > 0;
$attachmentFileInputAccept = implode(',', array_map(fn($ext) => '.' . $ext, $attachmentAcceptedExtensions));

$isGroupChat = (($chat->type ?? 'personal') === 'group');
$isPersonalChat = !$isGroupChat;
$canSendMessages = (bool)($canSendMessages ?? true);
$messageRestrictionReason = (string)($messageRestrictionReason ?? '');
$canStartCalls = (bool)($canStartCalls ?? true);
$messageDisabledNoticeText = $messageRestrictionReason === 'banned_user'
    ? "You can't send messages to a banned user."
    : 'Messaging is disabled in this private chat until you add each other as friends again.';
$canReportChat = ((int)$chat->created_by !== (int)$currentUserId);
$hasChatActions = $isGroupChat || $canReportChat;
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

    <div class="flex-1 flex flex-col h-full" id="chat-view" data-chat-id="<?= (int)$chat->id ?>" data-chat-number="<?= htmlspecialchars($chat->chat_number, ENT_QUOTES, 'UTF-8') ?>" data-chat-type="<?= htmlspecialchars($chat->type ?? 'personal', ENT_QUOTES, 'UTF-8') ?>" data-current-user-id="<?= (int)$currentUserId ?>" data-first-unseen-message-id="<?= (int)($firstUnseenMessageId ?? 0) ?>" data-personal-user-id="<?= (int)$personalChatUserId ?>" data-can-send-messages="<?= $canSendMessages ? '1' : '0' ?>" data-message-restriction-reason="<?= htmlspecialchars($messageRestrictionReason, ENT_QUOTES, 'UTF-8') ?>" data-can-start-calls="<?= $canStartCalls ? '1' : '0' ?>" data-current-username="<?= htmlspecialchars($currentUserUsername, ENT_QUOTES, 'UTF-8') ?>" data-peer-username="<?= htmlspecialchars($personalChatPeerUsername, ENT_QUOTES, 'UTF-8') ?>">
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
                <button type="button" data-chat-action="add-user" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-zinc-800">Add User</button>
                <button type="button" data-chat-action="rename-chat" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-zinc-800">Rename Chat</button>
                <button type="button" data-chat-action="leave-group" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-zinc-800 text-red-300">Leave Group</button>
                <?php endif; ?>
                <?php if ($canReportChat): ?>
                <button type="button" data-chat-action="report-chat" class="w-full text-left text-sm px-3 py-2 rounded-lg hover:bg-zinc-800 text-red-300">Report Chat</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="flex gap-6">
            <button id="start-voice-call-button" onclick="startVoiceCall()" class="prologue-accent hover:text-emerald-300 <?= $canStartCalls ? '' : 'opacity-50 cursor-not-allowed' ?>" <?= $canStartCalls ? '' : 'disabled title="You can\'t call a banned user"' ?>><i class="fa fa-phone text-2xl"></i></button>
        </div>
    </div>
    <?php if ($isGroupChat): ?>
    <div class="px-6 py-3 border-b border-zinc-800 text-sm">
        <div class="text-xs uppercase tracking-wide text-zinc-400 mb-2">Users in Group</div>
        <div class="flex flex-wrap items-center gap-2">
            <?php foreach (($members ?? []) as $member): ?>
                <?php if ((int)$member->id === (int)$currentUserId) { continue; } ?>
                <?php $friendLabel = ((int)($member->is_friend ?? 0) === 1) ? 'Friend' : 'Not Friend'; ?>
                <span class="inline-flex items-center gap-2 bg-zinc-800 border border-zinc-700 rounded-full px-3 py-1" data-group-member-user-id="<?= (int)$member->id ?>">
                    <a href="<?= htmlspecialchars(base_url('/u/' . User::formatUserNumber($member->user_number)), ENT_QUOTES, 'UTF-8') ?>" class="hover:underline underline-offset-2" title="<?= htmlspecialchars($friendLabel, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($friendLabel, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($member->username, ENT_QUOTES, 'UTF-8') ?></a>
                    <span class="inline-block w-1.5 h-1.5 rounded-full <?= htmlspecialchars($member->effective_status_dot_class ?? 'bg-zinc-500', ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($member->effective_status_label ?? 'Offline', ENT_QUOTES, 'UTF-8') ?>"></span>
                    <?php if ((int)$member->id !== (int)$chat->created_by): ?>
                    <button onclick='removeGroupMember(<?= (int)$member->id ?>, <?= json_encode((string)$member->username, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)' class="text-red-300 hover:text-red-200">×</button>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
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
        ?>
        <?php foreach ($messages as $index => $message): ?>
            <?php if ($message->is_system_event ?? false): ?>
                <?php
                    $isNewPrologueCluster = !$previousWasSystemEvent;
                    $sysFullTimestamp = (string)($message->created_at ?? '');
                    $sysCompactTimestamp = preg_replace('/:(\d{2})(?!.*:\d{2})/', '', $sysFullTimestamp);
                ?>
                <div class="flex gap-3 <?= $isNewPrologueCluster ? 'mt-4' : 'mt-1' ?>">
                    <div class="w-10 shrink-0">
                        <?php if ($isNewPrologueCluster): ?>
                            <div class="w-10 h-10 rounded-full border border-zinc-700 flex items-center justify-center font-semibold mt-0.5 bg-emerald-700 text-emerald-100">P</div>
                        <?php else: ?>
                            <div class="w-10 h-10"></div>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <?php if ($isNewPrologueCluster): ?>
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="text-sm font-semibold leading-5 prologue-accent">Prologue</span>
                            </div>
                        <?php endif; ?>
                        <div class="text-zinc-200 text-[17px] leading-6"><?= htmlspecialchars($message->content, ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="relative mt-0.5">
                            <div class="text-xs flex items-center gap-3">
                                <span class="text-zinc-500" data-utc="<?= htmlspecialchars($sysFullTimestamp, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($sysFullTimestamp, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($sysCompactTimestamp, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php $previousWasSystemEvent = true; $previousUserId = null; continue; ?>
            <?php endif; ?>
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
            <div class="flex gap-3 <?= $isNewGroup ? 'mt-4' : 'mt-1' ?>" data-message-id="<?= (int)$message->id ?>">
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
                    <?php if (!empty($message->attachments) && is_array($message->attachments)): ?>
                        <div class="mt-3 flex flex-wrap gap-3">
                            <?php foreach ($message->attachments as $attachment): ?>
                                <?php
                                    $attachmentExt = (string)($attachment->file_extension ?? '');
                                    $attachmentCategory = Attachment::extensionCategory($attachmentExt);
                                    $attachmentUrl = htmlspecialchars($attachment->url ?? '', ENT_QUOTES, 'UTF-8');
                                    $attachmentName = htmlspecialchars($attachment->original_name ?? 'Attachment', ENT_QUOTES, 'UTF-8');
                                    $attachmentDownloadName = htmlspecialchars(($attachment->file_name ?? '') . '.' . $attachmentExt, ENT_QUOTES, 'UTF-8');
                                    $attachmentSizeLabel = number_format(((int)$attachment->file_size) / 1024, 1) . ' KB';
                                    if ((int)$attachment->file_size >= 1024 * 1024) {
                                        $attachmentSizeLabel = number_format(((int)$attachment->file_size) / (1024 * 1024), 2) . ' MB';
                                    }
                                ?>
                                <div class="w-44 bg-zinc-800/70 border border-zinc-700 rounded-xl p-2">
                                    <?php if ($attachmentCategory === 'image'): ?>
                                        <button
                                            type="button"
                                            class="js-lightbox-trigger block w-full"
                                            data-image-url="<?= $attachmentUrl ?>"
                                            data-image-title="<?= $attachmentName ?>"
                                        >
                                            <img
                                                src="<?= $attachmentUrl ?>"
                                                alt="<?= $attachmentName ?>"
                                                class="w-full h-24 object-cover rounded-lg border border-zinc-700"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                        </button>
                                        <div class="mt-2 text-xs text-zinc-400 flex items-center justify-between gap-2">
                                            <span><?= htmlspecialchars($attachmentSizeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            <a href="<?= $attachmentUrl ?>" download="<?= $attachmentDownloadName ?>" class="text-zinc-300 hover:text-zinc-100" title="Download">
                                                <i class="fa-solid fa-download"></i>
                                            </a>
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
                                            <a href="<?= $attachmentUrl ?>" download="<?= $attachmentDownloadName ?>" class="text-zinc-300 hover:text-zinc-100 shrink-0" title="Download">
                                                <i class="fa-solid fa-download"></i>
                                            </a>
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
                                            <img src="<?= htmlspecialchars(base_url('/assets/emojis/' . $emojiFilename), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($emojiChar !== '' ? $emojiChar : ($reactionCodeToLabel[$reactionCode] ?? 'Reaction'), ENT_QUOTES, 'UTF-8') ?>" class="w-7 h-7" loading="lazy" decoding="async">
                                        <?php else: ?>
                                            <span><?= htmlspecialchars($emojiChar !== '' ? $emojiChar : ($reactionCodeToLabel[$reactionCode] ?? 'Reaction'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="text-xs flex items-center gap-3">
                            <span class="text-zinc-500" data-utc="<?= htmlspecialchars($fullTimestamp, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($fullTimestamp, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($compactTimestamp, ENT_QUOTES, 'UTF-8') ?></span>
                            <button type="button" class="text-zinc-400 hover:text-zinc-300 js-quote-link" data-quote-message-id="<?= (int)$message->id ?>" data-quote-username="<?= htmlspecialchars((string)$message->username, ENT_QUOTES, 'UTF-8') ?>" data-quote-user-number="<?= htmlspecialchars((string)$message->user_number, ENT_QUOTES, 'UTF-8') ?>" data-quote-content="<?= htmlspecialchars((string)$message->content, ENT_QUOTES, 'UTF-8') ?>" data-quote-mention-map="<?= htmlspecialchars($mentionMapJson, ENT_QUOTES, 'UTF-8') ?>">Quote</button>
                            <button type="button" class="text-zinc-400 hover:text-zinc-300 js-react-link" data-react-message-id="<?= (int)$message->id ?>">React</button>
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
                                                    <img src="<?= htmlspecialchars(base_url('/assets/emojis/' . $emojiFilename), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($emojiChar !== '' ? $emojiChar : $reactionTitlePrefix, ENT_QUOTES, 'UTF-8') ?>" class="w-6 h-6" loading="lazy" decoding="async">
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
            <button type="button" id="attachments-toggle" class="order-2 sm:order-1 w-[calc(50%-0.375rem)] sm:w-14 rounded-3xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 min-h-12" aria-label="Open attachments menu" aria-expanded="false" aria-controls="attachments-drawer">
                <i class="fa-solid fa-paperclip"></i>
            </button>
            <?php endif; ?>
            <button type="button" id="emoji-toggle" class="hidden lg:block order-3 sm:order-2 w-14 rounded-3xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700" aria-label="Open emoji picker" aria-expanded="false" aria-controls="emoji-drawer">
                <i class="fa-regular fa-face-smile"></i>
            </button>
            <input type="text" id="message-input" maxlength="16384" class="order-1 sm:order-3 w-full sm:flex-1 bg-zinc-800 border border-zinc-700 rounded-3xl px-6 py-4" placeholder="Message..." required>
            <button type="submit" class="order-2 sm:order-4 w-[calc(50%-0.375rem)] sm:w-auto bg-emerald-600 px-10 rounded-3xl min-h-12">Send</button>
        </div>
        <div id="message-disabled-notice" class="w-full bg-zinc-900 border border-zinc-700 rounded-2xl px-4 py-3 text-sm text-amber-300 <?= $canSendMessages ? 'hidden' : '' ?>"><?= htmlspecialchars($messageDisabledNoticeText, ENT_QUOTES, 'UTF-8') ?></div>
        <div id="emoji-drawer" class="hidden absolute left-6 bottom-[calc(100%+0.75rem)] w-[32rem] max-w-[calc(100%-3rem)] bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-4 z-30">
            <div class="flex gap-3 items-start">
                <div class="flex-1 min-w-0">
                    <input type="text" id="emoji-search" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2 text-sm mb-3" placeholder="Search emoji (type char or code, e.g. 1f44d)">
                    <div id="emoji-grid" class="grid grid-cols-6 justify-start gap-3 max-h-72 overflow-y-auto pr-1"></div>
                </div>
                <div id="emoji-preview" class="w-20 flex-shrink-0 flex flex-col items-center gap-2 pt-10">
                    <img id="emoji-preview-img" src="" alt="" class="w-14 h-14 hidden">
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
</div>

<script>
    window.OPENMOJI_FILES = <?= json_encode($emojiFileNames, JSON_UNESCAPED_SLASHES) ?>;
    window.OPENMOJI_METADATA = <?= json_encode($emojiMetadata, JSON_UNESCAPED_SLASHES) ?>;
    window.PENDING_ATTACHMENTS = <?= json_encode($pendingAttachments ?? [], JSON_UNESCAPED_SLASHES) ?>;
</script>

<?php if ($isGroupChat): ?>
<div id="add-user-modal" class="hidden fixed inset-0 bg-black/70 z-50 p-4 md:p-6" aria-hidden="true">
    <div class="h-full w-full flex items-center justify-center">
        <div class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-6" role="dialog" aria-modal="true" aria-labelledby="add-user-modal-title">
            <h2 id="add-user-modal-title" class="text-lg font-semibold text-zinc-100">Add user to group</h2>
            <p class="mt-2 text-sm text-zinc-400">Enter the username you want to add to this group chat.</p>
            <form id="add-user-form" class="mt-4 space-y-4">
                <label for="add-user-input" class="block text-sm text-zinc-300">Username</label>
                <input type="text" id="add-user-input" class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-2.5 text-zinc-100" placeholder="Enter username" required>
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
            <h2 id="rename-chat-modal-title" class="text-lg font-semibold text-zinc-100">Rename chat</h2>
            <p class="mt-2 text-sm text-zinc-400">Choose a new chat name. Leave empty to reset to chat number.</p>
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
<?php endif; ?>

<div id="attachment-lightbox" class="hidden fixed inset-0 bg-black/90 z-50 p-6">
    <button type="button" id="attachment-lightbox-close" class="absolute top-5 right-5 text-zinc-200 hover:text-white text-2xl" aria-label="Close image preview">
        <i class="fa-solid fa-xmark"></i>
    </button>
    <div class="h-full w-full flex items-center justify-center">
        <img id="attachment-lightbox-image" src="" alt="Attachment preview" class="max-h-full max-w-full rounded-xl border border-zinc-700">
    </div>
</div>