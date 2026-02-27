<?php
class ChatController extends Controller {
    private const GROUP_CHAT_USER_LIMIT = 200;
    private const USER_LIMIT_REACHED_MESSAGE = 'User limit reached. Please try again later.';

    private function getPinnedMessageSettingKey(int $chatId): string {
        return 'pinned_message_chat_' . $chatId;
    }

    private function clearPinnedMessageForChat(int $chatId): void {
        Database::query("DELETE FROM settings WHERE `key` = ?", [$this->getPinnedMessageSettingKey($chatId)]);
    }

    private function getPinnedMessageForChat(int $chatId) {
        if ($chatId <= 0) {
            return null;
        }

        $rawPinnedMessageId = (string)(Setting::get($this->getPinnedMessageSettingKey($chatId)) ?? '');
        $pinnedMessageId = (int)$rawPinnedMessageId;
        if ($pinnedMessageId <= 0) {
            return null;
        }

        try {
            $message = Database::query(
                "SELECT m.id, m.chat_id, m.user_id, m.content, m.created_at,
                        u.username, u.email AS user_email, u.user_number, u.avatar_filename
                 FROM messages m
                 JOIN users u ON u.id = m.user_id
                 WHERE m.id = ? AND m.chat_id = ?
                 LIMIT 1",
                [$pinnedMessageId, $chatId]
            )->fetch();
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'avatar_filename') === false) {
                throw $e;
            }

            $message = Database::query(
                "SELECT m.id, m.chat_id, m.user_id, m.content, m.created_at,
                        u.username, u.email AS user_email, u.user_number
                 FROM messages m
                 JOIN users u ON u.id = m.user_id
                 WHERE m.id = ? AND m.chat_id = ?
                 LIMIT 1",
                [$pinnedMessageId, $chatId]
            )->fetch();
        }

        if (!$message) {
            $this->clearPinnedMessageForChat($chatId);
            return null;
        }

        $message->username = User::decorateDeletedRetainedUsername($message->username ?? '', $message->user_email ?? null);

        $message->avatar_url = User::avatarUrl($message);
        $messages = [$message];
        Message::attachMentionMaps($messages);

        return $message;
    }

    private function isAcceptedFriendship(int $userId, int $otherUserId): bool {
        if ($userId <= 0 || $otherUserId <= 0 || $userId === $otherUserId) {
            return false;
        }

        $count = (int)Database::query(
            "SELECT COUNT(*) FROM friends
             WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
               AND status = 'accepted'",
            [$userId, $otherUserId, $otherUserId, $userId]
        )->fetchColumn();

        return $count > 0;
    }

    private function getPersonalChatPeerUserId(int $chatId, int $userId): int {
        $chat = Database::query("SELECT id, type FROM chats WHERE id = ?", [$chatId])->fetch();
        if (!$chat || Chat::isGroupType($chat->type ?? null)) {
            return 0;
        }

        $member = Database::query(
            "SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ? LIMIT 1",
            [$chatId, $userId]
        )->fetch();
        if (!$member) {
            return 0;
        }

        $peerUserId = (int)Database::query(
            "SELECT cm.user_id
             FROM chat_members cm
             WHERE cm.chat_id = ? AND cm.user_id != ?
             LIMIT 1",
            [$chatId, $userId]
        )->fetchColumn();

        return $peerUserId > 0 ? $peerUserId : 0;
    }

    private function getPersonalChatPeerMember(int $chatId, int $userId) {
        $chat = Database::query("SELECT id, type FROM chats WHERE id = ?", [$chatId])->fetch();
        if (!$chat || Chat::isGroupType($chat->type ?? null)) {
            return null;
        }

        $member = Database::query(
            "SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ? LIMIT 1",
            [$chatId, $userId]
        )->fetch();
        if (!$member) {
            return null;
        }

        $peer = Database::query(
            "SELECT u.id, u.is_banned
             FROM chat_members cm
             JOIN users u ON u.id = cm.user_id
             WHERE cm.chat_id = ? AND cm.user_id != ?
             LIMIT 1",
            [$chatId, $userId]
        )->fetch();

        return $peer ?: null;
    }

    private function getPersonalChatMessageRestrictionReason(int $chatId, int $userId): ?string {
        $peer = $this->getPersonalChatPeerMember($chatId, $userId);
        if (!$peer) {
            return null;
        }

        if ((int)($peer->is_banned ?? 0) === 1) {
            return 'banned_user';
        }

        $peerUserId = (int)($peer->id ?? 0);
        if ($peerUserId > 0 && !$this->isAcceptedFriendship($userId, $peerUserId)) {
            return 'not_friends';
        }

        return null;
    }

    private function canMessageInChat(int $chatId, int $userId): bool {
        return $this->getPersonalChatMessageRestrictionReason($chatId, $userId) === null;
    }

    private function supportsLastSeenMessageId(): bool {
        static $supports = null;
        if ($supports !== null) {
            return $supports;
        }

        $result = Database::query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_members' AND COLUMN_NAME = 'last_seen_message_id'"
        )->fetchColumn();

        $supports = ((int)$result) > 0;
        return $supports;
    }

    private function getFirstUnseenMessageId(int $chatId, int $lastSeenMessageId): int {
        $messageId = (int)Database::query(
            "SELECT id FROM messages WHERE chat_id = ? AND id > ? ORDER BY id ASC LIMIT 1",
            [$chatId, $lastSeenMessageId]
        )->fetchColumn();

        return $messageId > 0 ? $messageId : 0;
    }

    private function getLatestMessageId(int $chatId): int {
        $messageId = (int)Database::query(
            "SELECT id FROM messages WHERE chat_id = ? ORDER BY id DESC LIMIT 1",
            [$chatId]
        )->fetchColumn();

        return $messageId > 0 ? $messageId : 0;
    }

    private function supportsChatTitle(): bool {
        static $supports = null;
        if ($supports !== null) {
            return $supports;
        }

        $result = Database::query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chats' AND COLUMN_NAME = 'title'"
        )->fetchColumn();

        $supports = ((int)$result) > 0;
        return $supports;
    }

    private function supportsSystemEvents(): bool {
        static $supports = null;
        if ($supports !== null) {
            return $supports;
        }

        $result = Database::query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_system_events'"
        )->fetchColumn();

        $supports = ((int)$result) > 0;
        return $supports;
    }

    private function supportsChatSoftDelete(): bool {
        return Chat::supportsSoftDelete();
    }

    private function chatIsSoftDeleted($chat): bool {
        return Chat::isSoftDeleted($chat);
    }

    private function markChatSeenUpToMessageId(int $chatId, int $userId, int $messageId): void {
        if ($messageId <= 0) {
            return;
        }

        Database::query(
            "UPDATE chat_members
             SET last_seen_message_id = CASE
                 WHEN last_seen_message_id IS NULL OR last_seen_message_id < ? THEN ?
                 ELSE last_seen_message_id
             END
             WHERE chat_id = ? AND user_id = ?",
            [$messageId, $messageId, $chatId, $userId]
        );
    }

    public function show($params) {
        Auth::requireAuth();
        $authUser = Auth::user();
        $currentUserId = (int)$authUser->id;
        $isCurrentUserAdmin = strtolower((string)($authUser->role ?? '')) === 'admin';
        $chatNumber = (string)($params['chat_number'] ?? '');
        if (!preg_match('/^\d{4}-\d{4}-\d{4}-\d{4}$/', $chatNumber)) {
            $this->flash('error', 'invalid_chat');
            $this->redirect('/');
        }

        $chat = Database::query("SELECT * FROM chats WHERE chat_number = ?", [str_replace('-', '', $chatNumber)])->fetch();
        if (!$chat) {
            $this->flash('error', 'invalid_chat');
            $this->redirect('/');
        }

        if ($this->chatIsSoftDeleted($chat)) {
            $this->flash('error', 'invalid_chat');
            $this->redirect('/');
        }

        $chat->type = Chat::normalizeType($chat->type ?? null);

        $supportsLastSeen = $this->supportsLastSeenMessageId();
        $supportsChatTitle = $this->supportsChatTitle();

        $userId = $currentUserId;
        $member = Database::query("SELECT * FROM chat_members WHERE chat_id = ? AND user_id = ?", [$chat->id, $userId])->fetch();
        if (!$member) {
            $this->flash('error', 'invalid_chat');
            $this->redirect('/');
        }

        $lastSeenMessageId = $supportsLastSeen ? (int)($member->last_seen_message_id ?? 0) : 0;
        $firstUnseenMessageId = $supportsLastSeen ? $this->getFirstUnseenMessageId((int)$chat->id, $lastSeenMessageId) : 0;

        $members = Database::query(
            "SELECT u.id, u.username, u.user_number, u.avatar_filename, u.presence_status, u.last_active_at,
                    CASE
                        WHEN u.id = ? THEN 0
                        WHEN af.id IS NULL THEN 0
                        ELSE 1
                    END AS is_friend
             FROM chat_members cm
             JOIN users u ON u.id = cm.user_id
             LEFT JOIN friends af
               ON (
                    ((af.user_id = ? AND af.friend_id = u.id)
                     OR (af.friend_id = ? AND af.user_id = u.id))
                    AND af.status = 'accepted'
                  )
             WHERE cm.chat_id = ?
                         ORDER BY CASE WHEN u.id = ? THEN 0 ELSE 1 END ASC, u.username ASC",
                        [$currentUserId, $currentUserId, $currentUserId, $chat->id, (int)$chat->created_by]
        )->fetchAll();
        foreach ($members as $m) {
            $m->formatted_user_number = User::formatUserNumber($m->user_number);
            $m->avatar_url = User::avatarUrl($m);
            User::attachEffectiveStatus($m);
        }

        $chatTitle = 'Chat #' . User::formatUserNumber($chat->chat_number);
        if ($chat->type === 'personal') {
            $customTitle = $supportsChatTitle ? trim((string)($chat->title ?? '')) : '';
            if ($customTitle !== '') {
                $chatTitle = $customTitle;
            }

            $other = Database::query(
                "SELECT u.username
                 FROM chat_members cm
                 JOIN users u ON u.id = cm.user_id
                 WHERE cm.chat_id = ? AND cm.user_id != ?
                 LIMIT 1",
                [$chat->id, $currentUserId]
            )->fetch();
            if ($customTitle === '' && $other && isset($other->username)) {
                $chatTitle = $other->username;
            }
        } elseif ($chat->type === 'group') {
            $customTitle = $supportsChatTitle ? trim((string)($chat->title ?? '')) : '';
            if ($customTitle !== '') {
                $chatTitle = $customTitle;
            } else {
                $chatTitle = User::formatUserNumber($chat->chat_number);
            }
        }

        try {
            $messages = Database::query(
                "SELECT m.*, u.username, u.email AS user_email, u.user_number, u.avatar_filename, u.presence_status, u.last_active_at,
                        qu.username AS quoted_username, qu.user_number AS quoted_user_number
                 FROM messages m
                 JOIN users u ON m.user_id = u.id
                 LEFT JOIN users qu ON qu.id = m.quoted_user_id
                 WHERE m.chat_id = ?
                 ORDER BY m.created_at DESC
                 LIMIT 100",
                [$chat->id]
            )->fetchAll();
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'avatar_filename') === false) {
                throw $e;
            }

            $messages = Database::query(
                "SELECT m.*, u.username, u.email AS user_email, u.user_number, u.presence_status, u.last_active_at,
                        qu.username AS quoted_username, qu.user_number AS quoted_user_number
                 FROM messages m
                 JOIN users u ON m.user_id = u.id
                 LEFT JOIN users qu ON qu.id = m.quoted_user_id
                 WHERE m.chat_id = ?
                 ORDER BY m.created_at DESC
                 LIMIT 100",
                [$chat->id]
            )->fetchAll();
        }
        foreach ($messages as $message) {
            $message->username = User::decorateDeletedRetainedUsername($message->username ?? '', $message->user_email ?? null);
            $message->avatar_url = User::avatarUrl($message);
            User::attachEffectiveStatus($message);
        }
        Message::attachMentionMaps($messages);
        Message::attachQuoteMentionMaps($messages);
        Message::attachReactions($messages, (int)$currentUserId);
        Attachment::attachSubmittedToMessages($messages);

        if ($this->supportsSystemEvents()) {
            $systemEvents = Database::query(
                "SELECT id, chat_id, event_type, content, created_at, 1 AS is_system_event FROM chat_system_events WHERE chat_id = ?",
                [(int)$chat->id]
            )->fetchAll();

            $combined = array_merge($messages, $systemEvents);
            usort($combined, function ($a, $b) {
                return strcmp($b->created_at, $a->created_at);
            });
            $messages = array_slice($combined, 0, 100);
        }

        $messageRestrictionReason = $this->getPersonalChatMessageRestrictionReason((int)$chat->id, (int)$currentUserId);
        $canSendMessages = $messageRestrictionReason === null;
        $canStartCalls = $messageRestrictionReason !== 'banned_user';

        if ($supportsLastSeen) {
            $latestMessageId = $this->getLatestMessageId((int)$chat->id);
            $this->markChatSeenUpToMessageId((int)$chat->id, (int)$currentUserId, $latestMessageId);
        }

        $pendingAttachments = Attachment::listPendingForChatUser((int)$chat->id, (int)$currentUserId);
        $pinnedMessage = $this->getPinnedMessageForChat((int)$chat->id);

        $this->view('chat', [
            'chat' => $chat,
            'chatTitle' => $chatTitle,
            'messages' => array_reverse($messages),
            'members' => $members,
            'pendingAttachments' => $pendingAttachments,
            'currentUserId' => $currentUserId,
            'isCurrentUserAdmin' => $isCurrentUserAdmin,
            'firstUnseenMessageId' => $firstUnseenMessageId,
            'canSendMessages' => $canSendMessages,
            'messageRestrictionReason' => $messageRestrictionReason,
            'canStartCalls' => $canStartCalls,
            'pinnedMessage' => $pinnedMessage,
            'csrf' => $this->csrfToken()
        ]);
    }

    public function sendMessage() {
        Auth::requireAuth();
        Auth::csrfValidate();
        $chatId = (int)($_POST['chat_id'] ?? 0);
        $rawContent = trim($_POST['content'] ?? '');
        $attachmentIds = $this->parseAttachmentIds($_POST['attachment_ids'] ?? '');
        $quotedMessageId = (int)($_POST['quoted_message_id'] ?? 0);
        $userId = Auth::user()->id;

        if ($chatId <= 0 || $rawContent === '') {
            $this->json(['error' => 'Invalid payload'], 400);
        }

        if (mb_strlen($rawContent) > 16384) {
            $this->json(['error' => 'Message exceeds the maximum length of 16,384 characters'], 400);
        }

        $member = Database::query("SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?", [$chatId, $userId])->fetch();
        if (!$member) {
            $this->json(['error' => 'Access denied'], 403);
        }

        $chatSelect = $this->supportsChatSoftDelete()
            ? "SELECT chat_number, deleted_at FROM chats WHERE id = ?"
            : "SELECT chat_number FROM chats WHERE id = ?";
        $chat = Database::query($chatSelect, [$chatId])->fetch();
        if (!$chat) {
            $this->json(['error' => 'Chat not found'], 404);
        }

        if ($this->chatIsSoftDeleted($chat)) {
            $this->json(['error' => 'Chat not found'], 404);
        }

        $messageRestrictionReason = $this->getPersonalChatMessageRestrictionReason($chatId, (int)$userId);
        if ($messageRestrictionReason === 'banned_user') {
            $this->json(['error' => "You can't send messages to a banned user"], 403);
        }
        if ($messageRestrictionReason === 'not_friends') {
            $this->json(['error' => 'You can only message friends in personal chats'], 403);
        }

        $content = Message::encodeMentionsForChat($chatId, $rawContent);
        $quotedContent = null;
        $quotedUserId = null;
        $quotedUsername = '';

        if ($quotedMessageId > 0) {
            $quotedMessage = Database::query(
                "SELECT m.id, m.content, m.user_id, u.username
                 FROM messages m
                 JOIN users u ON u.id = m.user_id
                 WHERE m.id = ? AND m.chat_id = ?
                 LIMIT 1",
                [$quotedMessageId, $chatId]
            )->fetch();

            if (!$quotedMessage) {
                $this->json(['error' => 'Quoted message not found'], 404);
            }

            $quotedContent = (string)($quotedMessage->content ?? '');
            $quotedUserId = (int)($quotedMessage->user_id ?? 0);
            $quotedUsername = (string)($quotedMessage->username ?? '');
        }

        Database::query(
            "INSERT INTO messages (chat_id, user_id, content, quoted_message_id, quoted_user_id, quoted_content)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $chatId,
                $userId,
                $content,
                $quotedMessageId > 0 ? $quotedMessageId : null,
                $quotedUserId > 0 ? $quotedUserId : null,
                $quotedContent
            ]
        );
        $messageId = (int)Database::getInstance()->lastInsertId();
        Attachment::markPendingSubmitted($chatId, (int)$userId, $messageId, $attachmentIds);

        // Notify other members
        $members = Database::query("SELECT user_id FROM chat_members WHERE chat_id = ? AND user_id != ?", [$chatId, $userId])->fetchAll();
        foreach ($members as $m) {
            $notificationPreview = $rawContent;
            if ($quotedMessageId > 0 && $quotedUsername !== '') {
                $notificationPreview = '↪ ' . $quotedUsername . ' · ' . $notificationPreview;
            }
            Notification::create($m->user_id, 'message', 'New Message', mb_substr($notificationPreview, 0, 50), '/c/' . User::formatUserNumber($chat->chat_number));
        }

        $this->json(['success' => true]);
    }

    public function uploadAttachment() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $chatId = (int)($_POST['chat_id'] ?? 0);
        $user = Auth::user();
        $userId = (int)$user->id;

        if ($chatId <= 0) {
            $this->json(['error' => 'Invalid payload'], 400);
        }

        $member = Database::query("SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?", [$chatId, $userId])->fetch();
        if (!$member) {
            $this->json(['error' => 'Access denied'], 403);
        }

        if ($this->supportsChatSoftDelete()) {
            $chat = Database::query("SELECT id, deleted_at FROM chats WHERE id = ?", [$chatId])->fetch();
            if (!$chat || $this->chatIsSoftDeleted($chat)) {
                $this->json(['error' => 'Chat not found'], 404);
            }
        }

        $messageRestrictionReason = $this->getPersonalChatMessageRestrictionReason($chatId, $userId);
        if ($messageRestrictionReason === 'banned_user') {
            $this->json(['error' => "You can't send messages to a banned user"], 403);
        }
        if ($messageRestrictionReason === 'not_friends') {
            $this->json(['error' => 'You can only message friends in personal chats'], 403);
        }

        if (!isset($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
            $this->json(['error' => 'No file uploaded'], 400);
        }

        $result = Attachment::createPendingFromUpload($chatId, $user, $_FILES['attachment']);
        if (!empty($result['success'])) {
            $this->json($result);
        }

        $this->json(['error' => $result['error'] ?? 'attachment_upload_failed'], 400);
    }

    public function deleteAttachment() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $attachmentId = (int)($_POST['attachment_id'] ?? 0);
        $userId = (int)Auth::user()->id;

        if ($attachmentId <= 0) {
            $this->json(['error' => 'Invalid payload'], 400);
        }

        $deleted = Attachment::deletePendingByIdForUser($attachmentId, $userId);
        if (!$deleted) {
            $this->json(['error' => 'Attachment not found'], 404);
        }

        $this->json(['success' => true]);
    }

    public function getMessages($params) {
        Auth::requireAuth();
        $chatId = (int)($params['chat_id'] ?? 0);
        $userId = Auth::user()->id;

        $supportsLastSeen = $this->supportsLastSeenMessageId();

        $member = Database::query("SELECT * FROM chat_members WHERE chat_id = ? AND user_id = ?", [$chatId, $userId])->fetch();
        if (!$member) {
            $this->json(['error' => 'Access denied'], 403);
        }

        if ($this->supportsChatSoftDelete()) {
            $chat = Database::query("SELECT id, deleted_at FROM chats WHERE id = ?", [$chatId])->fetch();
            if (!$chat || $this->chatIsSoftDeleted($chat)) {
                $this->json(['error' => 'Chat not found'], 404);
            }
        }

        $lastSeenMessageId = $supportsLastSeen ? (int)($member->last_seen_message_id ?? 0) : 0;

        try {
            $messages = Database::query(
                "SELECT m.id, m.chat_id, m.user_id, m.content, m.created_at,
                        m.quoted_message_id, m.quoted_user_id, m.quoted_content,
                        u.username, u.email AS user_email, u.user_number, u.avatar_filename, u.presence_status, u.last_active_at,
                        qu.username AS quoted_username, qu.user_number AS quoted_user_number
                 FROM messages m
                 JOIN users u ON m.user_id = u.id
                 LEFT JOIN users qu ON qu.id = m.quoted_user_id
                 WHERE m.chat_id = ?
                 ORDER BY m.created_at ASC
                 LIMIT 200",
                [$chatId]
            )->fetchAll();
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'avatar_filename') === false) {
                throw $e;
            }

            $messages = Database::query(
                "SELECT m.id, m.chat_id, m.user_id, m.content, m.created_at,
                        m.quoted_message_id, m.quoted_user_id, m.quoted_content,
                        u.username, u.email AS user_email, u.user_number, u.presence_status, u.last_active_at,
                        qu.username AS quoted_username, qu.user_number AS quoted_user_number
                 FROM messages m
                 JOIN users u ON m.user_id = u.id
                 LEFT JOIN users qu ON qu.id = m.quoted_user_id
                 WHERE m.chat_id = ?
                 ORDER BY m.created_at ASC
                 LIMIT 200",
                [$chatId]
            )->fetchAll();
        }
        foreach ($messages as $message) {
            $message->username = User::decorateDeletedRetainedUsername($message->username ?? '', $message->user_email ?? null);
            $message->avatar_url = User::avatarUrl($message);
            User::attachEffectiveStatus($message);
        }
        Message::attachMentionMaps($messages);
        Message::attachQuoteMentionMaps($messages);
        Message::attachReactions($messages, (int)$userId);
        Attachment::attachSubmittedToMessages($messages);

        $firstUnseenMessageId = 0;
        if ($supportsLastSeen) {
            $firstUnseenMessageId = $this->getFirstUnseenMessageId($chatId, $lastSeenMessageId);
            $latestMessageId = !empty($messages) ? (int)($messages[count($messages) - 1]->id ?? 0) : 0;
            $this->markChatSeenUpToMessageId($chatId, (int)$userId, $latestMessageId);
        }

        $messageRestrictionReason = $this->getPersonalChatMessageRestrictionReason($chatId, (int)$userId);
        $canSendMessage = $messageRestrictionReason === null;
        $canStartCall = $messageRestrictionReason !== 'banned_user';

        if ($this->supportsSystemEvents()) {
            $chatRow = Database::query("SELECT type FROM chats WHERE id = ?", [$chatId])->fetch();
            if ($chatRow && Chat::isGroupType($chatRow->type ?? null)) {
                $systemEvents = Database::query(
                    "SELECT id, chat_id, event_type, content, created_at, 1 AS is_system_event FROM chat_system_events WHERE chat_id = ?",
                    [(int)$chatId]
                )->fetchAll();
                $combined = array_merge($messages, $systemEvents);
                usort($combined, function ($a, $b) {
                    return strcmp($a->created_at, $b->created_at);
                });
                $messages = array_values(array_slice($combined, -200));
            }
        }

        $this->json([
            'messages' => $messages,
            'first_unseen_message_id' => $firstUnseenMessageId,
            'can_send_message' => $canSendMessage,
            'can_send_message_reason' => $messageRestrictionReason,
            'can_start_call' => $canStartCall
        ]);
    }

    public function reactMessage() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $messageId = (int)($_POST['message_id'] ?? 0);
        $reactionCode = Message::normalizeReactionCode($_POST['reaction_code'] ?? '');
        $userId = (int)Auth::user()->id;

        if ($messageId <= 0) {
            $this->json(['error' => 'Invalid payload'], 400);
        }
        if ($reactionCode === '') {
            $this->json(['error' => 'Invalid reaction'], 400);
        }

        $message = Database::query(
            "SELECT m.id, m.chat_id
             FROM messages m
             JOIN chat_members cm ON cm.chat_id = m.chat_id AND cm.user_id = ?
             WHERE m.id = ?
             LIMIT 1",
            [$userId, $messageId]
        )->fetch();
        if (!$message) {
            $this->json(['error' => 'Message not found'], 404);
        }

        if ($this->supportsChatSoftDelete()) {
            $chat = Database::query("SELECT id, deleted_at FROM chats WHERE id = ?", [(int)$message->chat_id])->fetch();
            if (!$chat || $this->chatIsSoftDeleted($chat)) {
                $this->json(['error' => 'Message not found'], 404);
            }
        }

        $existing = Database::query(
            "SELECT reaction_code FROM message_reactions WHERE message_id = ? AND user_id = ? LIMIT 1",
            [$messageId, $userId]
        )->fetch();

        $action = 'set';
        if ($existing) {
            $existingCode = Message::normalizeReactionCode($existing->reaction_code ?? '');
            if ($existingCode === $reactionCode) {
                Database::query("DELETE FROM message_reactions WHERE message_id = ? AND user_id = ?", [$messageId, $userId]);
                $action = 'removed';
            } else {
                Database::query(
                    "UPDATE message_reactions SET reaction_code = ? WHERE message_id = ? AND user_id = ?",
                    [$reactionCode, $messageId, $userId]
                );
            }
        } else {
            Database::query(
                "INSERT INTO message_reactions (message_id, user_id, reaction_code) VALUES (?, ?, ?)",
                [$messageId, $userId, $reactionCode]
            );
        }

        $this->json(['success' => true, 'action' => $action]);
    }

    public function createGroup() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $authUser = Auth::user();
        $currentUserId = (int)$authUser->id;
        $actorUsername = User::normalizeUsername($authUser->username ?? '');
        $memberIds = [$currentUserId => true];

        $chatNumber = Chat::generateUniqueChatNumber();
        Database::query("INSERT INTO chats (chat_number, type, created_by) VALUES (?, 'group', ?)", [$chatNumber, $currentUserId]);
        $chatId = (int)Database::getInstance()->lastInsertId();

        $params = [];
        $values = [];
        foreach (array_keys($memberIds) as $memberId) {
            $values[] = '(?, ?)';
            $params[] = $chatId;
            $params[] = (int)$memberId;
        }
        Database::query('INSERT INTO chat_members (chat_id, user_id) VALUES ' . implode(', ', $values), $params);

        if ($this->supportsSystemEvents() && $actorUsername !== '') {
            Database::query(
                "INSERT INTO chat_system_events (chat_id, event_type, content) VALUES (?, 'group_created', ?)",
                [$chatId, $actorUsername . ' created the group']
            );
        }

        $this->json(['success' => true, 'chat_number' => $chatNumber]);
    }

    public function addGroupMember() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $chatId = (int)($_POST['chat_id'] ?? 0);
        $targetUsername = User::normalizeUsername($_POST['username'] ?? '');
        $authUser = Auth::user();
        $currentUserId = (int)$authUser->id;
        $actorUsername = User::normalizeUsername($authUser->username ?? '');

        if ($chatId <= 0 || $targetUsername === '') {
            $this->json(['error' => 'Invalid payload'], 400);
        }

        $chatSelect = $this->supportsChatSoftDelete()
            ? "SELECT id, type, created_by, deleted_at FROM chats WHERE id = ?"
            : "SELECT id, type, created_by FROM chats WHERE id = ?";
        $chat = Database::query($chatSelect, [$chatId])->fetch();
        if (!$chat) {
            $this->json(['error' => 'Chat not found'], 404);
        }

        if ($this->chatIsSoftDeleted($chat)) {
            $this->json(['error' => 'Chat not found'], 404);
        }

        if (!Chat::isGroupType($chat->type ?? null)) {
            $this->json(['error' => 'Personal chats cannot add or remove users'], 403);
        }

        $member = Database::query("SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?", [$chatId, $currentUserId])->fetch();
        if (!$member) {
            $this->json(['error' => 'Access denied'], 403);
        }

        $target = User::findByUsername($targetUsername);
        if (!$target) {
            $this->json(['error' => 'User not found'], 404);
        }

        $targetUserId = (int)$target->id;
        $existingTargetMember = Database::query(
            "SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ? LIMIT 1",
            [$chatId, $targetUserId]
        )->fetch();

        if (!$existingTargetMember) {
            $memberCount = (int)Database::query(
                "SELECT COUNT(*) FROM chat_members WHERE chat_id = ?",
                [$chatId]
            )->fetchColumn();

            if ($memberCount >= self::GROUP_CHAT_USER_LIMIT) {
                $this->json(['error' => self::USER_LIMIT_REACHED_MESSAGE], 429);
            }
        }

        Database::query("INSERT IGNORE INTO chat_members (chat_id, user_id) VALUES (?, ?)", [$chatId, $targetUserId]);
        if (!$existingTargetMember && $this->supportsLastSeenMessageId()) {
            $latestMessageId = $this->getLatestMessageId($chatId);
            if ($latestMessageId > 0) {
                $this->markChatSeenUpToMessageId($chatId, $targetUserId, $latestMessageId);
            }
        }
        if (!$existingTargetMember && $this->supportsSystemEvents()) {
            $targetDisplayUsername = User::normalizeUsername($target->username ?? '');
            if ($actorUsername !== '' && $targetDisplayUsername !== '') {
                Database::query(
                    "INSERT INTO chat_system_events (chat_id, event_type, content) VALUES (?, 'user_added', ?)",
                    [$chatId, $actorUsername . ' added ' . $targetDisplayUsername . ' to group']
                );
            }
        }
        $this->json(['success' => true]);
    }

    public function removeGroupMember() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $chatId = (int)($_POST['chat_id'] ?? 0);
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $authUser = Auth::user();
        $currentUserId = (int)$authUser->id;
        $actorUsername = User::normalizeUsername($authUser->username ?? '');

        if ($chatId <= 0 || $targetUserId <= 0) {
            $this->json(['error' => 'Invalid payload'], 400);
        }

        $chatSelect = $this->supportsChatSoftDelete()
            ? "SELECT id, type, chat_number, created_by, deleted_at FROM chats WHERE id = ?"
            : "SELECT id, type, chat_number, created_by FROM chats WHERE id = ?";
        $chat = Database::query($chatSelect, [$chatId])->fetch();
        if (!$chat) {
            $this->json(['error' => 'Chat not found'], 404);
        }

        if ($this->chatIsSoftDeleted($chat)) {
            $this->json(['error' => 'Chat not found'], 404);
        }

        if (!Chat::isGroupType($chat->type ?? null)) {
            $this->json(['error' => 'Personal chats cannot add or remove users'], 403);
        }

        $member = Database::query("SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?", [$chatId, $currentUserId])->fetch();
        if (!$member) {
            $this->json(['error' => 'Access denied'], 403);
        }

        $ownerUserId = (int)($chat->created_by ?? 0);
        if ($ownerUserId > 0 && $ownerUserId === $targetUserId) {
            $this->json(['error' => 'Group owner cannot be removed'], 403);
        }

        $targetUser = Database::query(
            "SELECT id, username FROM users WHERE id = ? LIMIT 1",
            [$targetUserId]
        )->fetch();
        $targetMember = Database::query(
            "SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ? LIMIT 1",
            [$chatId, $targetUserId]
        )->fetch();

        if (!$targetMember) {
            $this->json(['error' => 'User is not in this group'], 404);
        }

        Database::query("DELETE FROM chat_members WHERE chat_id = ? AND user_id = ?", [$chatId, $targetUserId]);
        if ($targetMember && $this->supportsSystemEvents()) {
            $targetDisplayUsername = User::normalizeUsername($targetUser->username ?? '');
            if ($actorUsername !== '' && $targetDisplayUsername !== '') {
                Database::query(
                    "INSERT INTO chat_system_events (chat_id, event_type, content) VALUES (?, 'user_removed', ?)",
                    [$chatId, $actorUsername . ' removed ' . $targetDisplayUsername . ' from group']
                );
            }
        }
        $remaining = (int)Database::query("SELECT COUNT(*) FROM chat_members WHERE chat_id = ?", [$chatId])->fetchColumn();
        if ($remaining <= 0) {
            Database::query("DELETE FROM chats WHERE id = ?", [$chatId]);
        }

        $this->json(['success' => true]);
    }

    public function leaveGroup() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $chatId = (int)($_POST['chat_id'] ?? 0);
        $newOwnerUserId = (int)($_POST['new_owner_user_id'] ?? 0);
        $authUser = Auth::user();
        $currentUserId = (int)$authUser->id;
        $actorUsername = User::normalizeUsername($authUser->username ?? '');

        if ($chatId <= 0) {
            $this->json(['error' => 'Invalid payload'], 400);
        }

        $chatSelect = $this->supportsChatSoftDelete()
            ? "SELECT id, type, created_by, deleted_at FROM chats WHERE id = ?"
            : "SELECT id, type, created_by FROM chats WHERE id = ?";
        $chat = Database::query($chatSelect, [$chatId])->fetch();
        if (!$chat) {
            $this->json(['error' => 'Chat not found'], 404);
        }

        if ($this->chatIsSoftDeleted($chat)) {
            $this->json(['error' => 'Chat not found'], 404);
        }

        if (!Chat::isGroupType($chat->type ?? null)) {
            $this->json(['error' => 'Only group chats can be left'], 403);
        }

        $member = Database::query(
            "SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ? LIMIT 1",
            [$chatId, $currentUserId]
        )->fetch();
        if (!$member) {
            $this->json(['error' => 'Access denied'], 403);
        }

        if ((int)($chat->created_by ?? 0) === $currentUserId) {
            $eligibleNewOwners = Database::query(
                "SELECT cm.user_id, u.username
                 FROM chat_members cm
                 JOIN users u ON u.id = cm.user_id
                 WHERE cm.chat_id = ? AND cm.user_id != ?
                 ORDER BY u.username ASC",
                [$chatId, $currentUserId]
            )->fetchAll();

            if (count($eligibleNewOwners) === 0) {
                $this->json(['error' => 'You cannot leave a group you own with no other members. Delete the group instead.'], 403);
            }

            $newOwnerIsEligible = false;
            foreach ($eligibleNewOwners as $eligibleNewOwner) {
                if ((int)($eligibleNewOwner->user_id ?? 0) === $newOwnerUserId) {
                    $newOwnerIsEligible = true;
                    break;
                }
            }

            if (!$newOwnerIsEligible) {
                $this->json(['error' => 'Choose a current group member to transfer ownership before leaving.'], 400);
            }

            Database::query("UPDATE chats SET created_by = ? WHERE id = ?", [$newOwnerUserId, $chatId]);
            if ($this->supportsSystemEvents() && $actorUsername !== '') {
                $newOwnerUsername = (string)Database::query("SELECT username FROM users WHERE id = ?", [$newOwnerUserId])->fetchColumn();
                if ($newOwnerUsername !== '') {
                    Database::query(
                        "INSERT INTO chat_system_events (chat_id, event_type, content) VALUES (?, 'ownership_transferred', ?)",
                        [$chatId, $actorUsername . ' transferred group ownership to ' . User::normalizeUsername($newOwnerUsername)]
                    );
                }
            }
        }

        Database::query("DELETE FROM chat_members WHERE chat_id = ? AND user_id = ?", [$chatId, $currentUserId]);

        $remaining = (int)Database::query("SELECT COUNT(*) FROM chat_members WHERE chat_id = ?", [$chatId])->fetchColumn();
        if ($remaining > 0 && $this->supportsSystemEvents() && $actorUsername !== '') {
            Database::query(
                "INSERT INTO chat_system_events (chat_id, event_type, content) VALUES (?, 'user_left', ?)",
                [$chatId, $actorUsername . ' left the group']
            );
        }

        if ($remaining <= 0) {
            Database::query("DELETE FROM chats WHERE id = ?", [$chatId]);
        }

        $this->json(['success' => true, 'redirect' => '/']);
    }

    public function renameChat() {
        Auth::requireAuth();
        Auth::csrfValidate();

        if (!$this->supportsChatTitle()) {
            $this->json(['error' => 'Chat rename is not available until the database update is applied'], 400);
        }

        $chatId = (int)($_POST['chat_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $currentUserId = (int)Auth::user()->id;

        if ($chatId <= 0) {
            $this->json(['error' => 'Invalid payload'], 400);
        }

        if (mb_strlen($title) > 80) {
            $this->json(['error' => 'Chat name must be 80 characters or fewer'], 400);
        }

        $chatSelect = $this->supportsChatSoftDelete()
            ? "SELECT id, type, chat_number, title, created_by, deleted_at FROM chats WHERE id = ?"
            : "SELECT id, type, chat_number, title, created_by FROM chats WHERE id = ?";
        $chat = Database::query($chatSelect, [$chatId])->fetch();
        if (!$chat) {
            $this->json(['error' => 'Chat not found'], 404);
        }

        if ($this->chatIsSoftDeleted($chat)) {
            $this->json(['error' => 'Chat not found'], 404);
        }

        if (!Chat::isGroupType($chat->type ?? null)) {
            $this->json(['error' => 'Only group chats can be renamed'], 403);
        }

        if ((int)($chat->created_by ?? 0) !== $currentUserId) {
            $this->json(['error' => 'Only the group owner can rename this chat'], 403);
        }

        $member = Database::query("SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?", [$chatId, $currentUserId])->fetch();
        if (!$member) {
            $this->json(['error' => 'Access denied'], 403);
        }

        $oldTitle = trim((string)($chat->title ?? ''));
        if ($oldTitle === '') {
            $oldTitle = User::formatUserNumber($chat->chat_number);
        }

        if ($title === '') {
            Database::query("UPDATE chats SET title = NULL WHERE id = ?", [$chatId]);
            $newTitle = User::formatUserNumber($chat->chat_number);
            if ($this->supportsSystemEvents()) {
                Database::query(
                    "INSERT INTO chat_system_events (chat_id, event_type, content) VALUES (?, 'chat_renamed', ?)",
                    [$chatId, 'Chat renamed from ' . $oldTitle . ' to ' . $newTitle]
                );
            }
            $this->json(['success' => true, 'title' => $newTitle, 'reset' => true]);
        }

        Database::query("UPDATE chats SET title = ? WHERE id = ?", [$title, $chatId]);
        if ($this->supportsSystemEvents()) {
            Database::query(
                "INSERT INTO chat_system_events (chat_id, event_type, content) VALUES (?, 'chat_renamed', ?)",
                [$chatId, 'Chat renamed from ' . $oldTitle . ' to ' . $title]
            );
        }
        $this->json(['success' => true, 'title' => $title, 'reset' => false]);
    }

    public function deleteGroup() {
        Auth::requireAuth();
        Auth::csrfValidate();

        if (!$this->supportsChatSoftDelete()) {
            $this->json(['error' => 'Group delete requires database update for chat trash support'], 400);
        }

        $chatId = (int)($_POST['chat_id'] ?? 0);
        $currentUserId = (int)Auth::user()->id;

        if ($chatId <= 0) {
            $this->json(['error' => 'Invalid payload'], 400);
        }

        $chat = Database::query(
            "SELECT id, type, created_by, deleted_at FROM chats WHERE id = ?",
            [$chatId]
        )->fetch();
        if (!$chat) {
            $this->json(['error' => 'Chat not found'], 404);
        }

        if ($this->chatIsSoftDeleted($chat)) {
            $this->json(['error' => 'Chat already deleted'], 400);
        }

        if (!Chat::isGroupType($chat->type ?? null)) {
            $this->json(['error' => 'Only group chats can be deleted'], 403);
        }

        if ((int)($chat->created_by ?? 0) !== $currentUserId) {
            $this->json(['error' => 'Only the group owner can delete this group'], 403);
        }

        $member = Database::query(
            "SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ? LIMIT 1",
            [$chatId, $currentUserId]
        )->fetch();
        if (!$member) {
            $this->json(['error' => 'Access denied'], 403);
        }

        if (Chat::supportsDeletedBy()) {
            Database::query(
                "UPDATE chats SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND deleted_at IS NULL",
                [$currentUserId, $chatId]
            );
        } else {
            Database::query(
                "UPDATE chats SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL",
                [$chatId]
            );
        }

        $this->json(['success' => true, 'redirect' => '/']);
    }

    public function deletePersonalByNumber($params) {
        Auth::requireAuth();

        $chat = $this->findAccessiblePersonalChatByNumber((string)($params['chat_number'] ?? ''), (int)Auth::user()->id);
        if (!$chat) {
            $this->redirect('/');
        }

        $chatNumberFormatted = User::formatUserNumber((string)$chat->chat_number);
        $supportsChatTitle = $this->supportsChatTitle();
        $chatTitle = 'Personal Chat ' . $chatNumberFormatted;
        $customTitle = $supportsChatTitle ? trim((string)($chat->title ?? '')) : '';
        if ($customTitle !== '') {
            $chatTitle = $customTitle;
        } else {
            $otherUsername = (string)Database::query(
                "SELECT u.username
                 FROM chat_members cm
                 JOIN users u ON u.id = cm.user_id
                 WHERE cm.chat_id = ? AND cm.user_id != ?
                 ORDER BY cm.joined_at ASC
                 LIMIT 1",
                [(int)$chat->id, (int)Auth::user()->id]
            )->fetchColumn();
            $normalizedOtherUsername = trim($otherUsername);
            if ($normalizedOtherUsername !== '') {
                $chatTitle = $normalizedOtherUsername;
            }
        }

        $this->view('chat_delete_confirm', [
            'chat' => $chat,
            'chatNumberFormatted' => $chatNumberFormatted,
            'chatTitle' => $chatTitle,
            'csrf' => $this->csrfToken()
        ]);
    }

    public function deletePersonalByNumberConfirm($params) {
        Auth::requireAuth();
        Auth::csrfValidate();

        $chat = $this->findAccessiblePersonalChatByNumber((string)($params['chat_number'] ?? ''), (int)Auth::user()->id);
        if (!$chat) {
            $this->redirect('/');
        }

        $this->permanentlyDeletePersonalChat((int)$chat->id);
        $this->redirect('/');
    }

    private function findAccessiblePersonalChatByNumber(string $chatNumber, int $currentUserId) {
        if (!preg_match('/^\d{4}-\d{4}-\d{4}-\d{4}$/', $chatNumber) || $currentUserId <= 0) {
            return null;
        }

        $rawChatNumber = str_replace('-', '', $chatNumber);

        $chatSelect = $this->supportsChatSoftDelete()
            ? "SELECT id, chat_number, type, title, deleted_at FROM chats WHERE chat_number = ? LIMIT 1"
            : "SELECT id, chat_number, type, title FROM chats WHERE chat_number = ? LIMIT 1";
        $chat = Database::query($chatSelect, [$rawChatNumber])->fetch();
        if (!$chat || $this->chatIsSoftDeleted($chat) || Chat::isGroupType($chat->type ?? null)) {
            return null;
        }

        $member = Database::query(
            "SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ? LIMIT 1",
            [(int)$chat->id, $currentUserId]
        )->fetch();
        if (!$member) {
            return null;
        }

        return $chat;
    }

    private function permanentlyDeletePersonalChat(int $chatId): void {
        if ($chatId <= 0) {
            return;
        }

        Attachment::deleteFilesForChatId($chatId);

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();

            Database::query(
                "DELETE FROM call_participants
                 WHERE call_id IN (SELECT id FROM calls WHERE chat_id = ?)",
                [$chatId]
            );
            try {
                Database::query(
                    "DELETE FROM call_signals
                     WHERE call_id IN (SELECT id FROM calls WHERE chat_id = ?)",
                    [$chatId]
                );
            } catch (Throwable $e) {
                if (stripos($e->getMessage(), 'call_signals') === false) {
                    throw $e;
                }
            }
            Database::query("DELETE FROM calls WHERE chat_id = ?", [$chatId]);
            Database::query("DELETE FROM chats WHERE id = ?", [$chatId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    public function takeGroupOwnership() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $chatId = (int)($_POST['chat_id'] ?? 0);
        $authUser = Auth::user();
        $currentUserId = (int)$authUser->id;
        $isCurrentUserAdmin = strtolower((string)($authUser->role ?? '')) === 'admin';
        $actorUsername = User::normalizeUsername($authUser->username ?? '');

        if ($chatId <= 0) {
            $this->json(['error' => 'Invalid payload'], 400);
        }

        if (!$isCurrentUserAdmin) {
            $this->json(['error' => 'Only admins can take group ownership'], 403);
        }

        $chatSelect = $this->supportsChatSoftDelete()
            ? "SELECT id, type, created_by, deleted_at FROM chats WHERE id = ?"
            : "SELECT id, type, created_by FROM chats WHERE id = ?";
        $chat = Database::query($chatSelect, [$chatId])->fetch();
        if (!$chat) {
            $this->json(['error' => 'Chat not found'], 404);
        }

        if ($this->chatIsSoftDeleted($chat)) {
            $this->json(['error' => 'Chat not found'], 404);
        }

        if (!Chat::isGroupType($chat->type ?? null)) {
            $this->json(['error' => 'Only group chats can change ownership'], 403);
        }

        $member = Database::query(
            "SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ? LIMIT 1",
            [$chatId, $currentUserId]
        )->fetch();
        if (!$member) {
            $this->json(['error' => 'Access denied'], 403);
        }

        $ownerUserId = (int)($chat->created_by ?? 0);
        if ($ownerUserId === $currentUserId) {
            $this->json(['error' => 'You already own this group'], 400);
        }

        Database::query("UPDATE chats SET created_by = ? WHERE id = ?", [$currentUserId, $chatId]);

        if ($this->supportsSystemEvents() && $actorUsername !== '') {
            $eventContent = $actorUsername . ' took group ownership';
            if ($ownerUserId > 0) {
                $previousOwnerUsername = (string)Database::query(
                    "SELECT username FROM users WHERE id = ? LIMIT 1",
                    [$ownerUserId]
                )->fetchColumn();
                $normalizedPreviousOwner = User::normalizeUsername($previousOwnerUsername);
                if ($normalizedPreviousOwner !== '' && $normalizedPreviousOwner !== $actorUsername) {
                    $eventContent .= ' from ' . $normalizedPreviousOwner;
                }
            }

            Database::query(
                "INSERT INTO chat_system_events (chat_id, event_type, content) VALUES (?, 'ownership_taken', ?)",
                [$chatId, $eventContent]
            );
        }

        $this->json(['success' => true]);
    }

    private function parseAttachmentIds($raw): array {
        $value = trim((string)$raw);
        if ($value === '') {
            return [];
        }

        $tokens = preg_split('/[^0-9]+/', $value) ?: [];
        $ids = [];
        foreach ($tokens as $token) {
            $id = (int)$token;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }
}