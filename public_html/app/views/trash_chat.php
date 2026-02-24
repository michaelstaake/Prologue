<?php
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

<div class="p-8 overflow-auto">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold">Trash Chat</h1>
            <p class="text-sm text-zinc-400 mt-1"><?= htmlspecialchars((string)$chatTitle, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(User::formatUserNumber((string)$chat->chat_number), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= htmlspecialchars(base_url('/trash'), ENT_QUOTES, 'UTF-8') ?>" class="bg-zinc-700 hover:bg-zinc-600 px-4 py-2 rounded-xl text-sm">Back to Trash</a>
            <button
                type="button"
                class="bg-red-700 hover:bg-red-600 text-white text-sm px-4 py-2 rounded-xl js-trash-delete-open"
                data-chat-id="<?= (int)$chat->id ?>"
                data-chat-title="<?= htmlspecialchars((string)$chatTitle, ENT_QUOTES, 'UTF-8') ?>"
            >Delete Permanently</button>
        </div>
    </div>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-5 max-w-5xl mb-6">
        <div class="text-sm text-zinc-300">
            <div>Deleted at: <span class="text-zinc-100"><?= htmlspecialchars((string)$chat->deleted_at, ENT_QUOTES, 'UTF-8') ?></span></div>
            <div class="mt-1">Deleted by: <span class="text-zinc-100"><?= htmlspecialchars((string)($chat->deleted_by_username ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?></span></div>
            <div class="mt-1">Created by: <span class="text-zinc-100"><?= htmlspecialchars((string)($chat->created_by_username ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?></span></div>
        </div>
    </section>

    <section class="bg-zinc-900 border border-zinc-700 rounded-2xl p-5 max-w-5xl">
        <h2 class="text-lg font-semibold mb-4">Messages</h2>
        <div class="space-y-3">
            <?php if (empty($messages ?? [])): ?>
                <p class="text-zinc-400 text-sm">No messages.</p>
            <?php else: ?>
                <?php foreach (($messages ?? []) as $message): ?>
                    <?php if ($message->is_system_event ?? false): ?>
                        <div class="bg-zinc-800/50 border border-zinc-700 rounded-xl p-3 text-sm text-zinc-300">
                            <div class="text-zinc-400 text-xs mb-1">System · <?= htmlspecialchars((string)$message->created_at, ENT_QUOTES, 'UTF-8') ?></div>
                            <div><?= htmlspecialchars((string)$message->content, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <?php continue; ?>
                    <?php endif; ?>

                    <?php
                        $mentionMap = $message->mention_map ?? (object)[];
                        $content = $renderStoredMentionsToPlain((string)($message->content ?? ''), $mentionMap);
                        $avatarUrl = $message->avatar_url ?? User::avatarUrl($message);
                    ?>
                    <div class="bg-zinc-800 rounded-xl p-3">
                        <div class="flex items-start gap-3">
                            <?php if (!empty($avatarUrl)): ?>
                                <img src="<?= htmlspecialchars((string)$avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)$message->username, ENT_QUOTES, 'UTF-8') ?> avatar" class="w-9 h-9 rounded-full object-cover border border-zinc-700">
                            <?php else: ?>
                                <div class="w-9 h-9 rounded-full border border-zinc-700 flex items-center justify-center font-semibold text-xs <?= htmlspecialchars(User::avatarColorClasses((string)$message->user_number), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(User::avatarInitial((string)$message->username), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-zinc-100"><?= htmlspecialchars((string)$message->username, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-xs text-zinc-500"><?= htmlspecialchars((string)$message->created_at, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="text-sm text-zinc-200 mt-1 break-words"><?= nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')) ?></div>

                                <?php if (!empty($message->attachments) && is_array($message->attachments)): ?>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <?php foreach ($message->attachments as $attachment): ?>
                                            <a href="<?= htmlspecialchars((string)($attachment->url ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 text-xs bg-zinc-900 border border-zinc-700 rounded-lg px-2 py-1 hover:bg-zinc-800" download>
                                                <i class="fa-solid fa-paperclip"></i>
                                                <span><?= htmlspecialchars((string)($attachment->original_name ?? 'Attachment'), ENT_QUOTES, 'UTF-8') ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<div id="trash-delete-modal" class="hidden fixed inset-0 bg-black/70 z-50 p-4 md:p-6" aria-hidden="true">
    <div class="h-full w-full flex items-center justify-center">
        <div class="w-full max-w-md bg-zinc-900 border border-zinc-700 rounded-2xl shadow-2xl p-6" role="dialog" aria-modal="true" aria-labelledby="trash-delete-modal-title">
            <h2 id="trash-delete-modal-title" class="text-lg font-semibold text-zinc-100">Delete chat permanently</h2>
            <p class="mt-2 text-sm text-zinc-400" id="trash-delete-modal-description">Are you sure you want to permanently delete this chat and all associated data?</p>
            <form id="trash-delete-form" action="<?= htmlspecialchars(base_url('/trash/delete'), ENT_QUOTES, 'UTF-8') ?>" method="POST" class="mt-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" id="trash-delete-chat-id" name="chat_id" value="">
                <div class="flex items-center justify-end gap-3">
                    <button type="button" id="trash-delete-cancel" class="px-4 py-2 rounded-xl bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-zinc-200">Cancel</button>
                    <button type="submit" id="trash-delete-submit" class="px-4 py-2 rounded-xl bg-red-700 hover:bg-red-600 text-white">Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>
</div>
