<?php
class ApiController extends Controller {
    private function supportsChatSoftDelete(): bool {
        return Chat::supportsSoftDelete();
    }

    private function chatIsSoftDeleted($chat): bool {
        return Chat::isSoftDeleted($chat);
    }

    private function ensureCallSignalTableExists(): void {
        Database::query(
            "CREATE TABLE IF NOT EXISTS call_signals (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                call_id INT NOT NULL,
                from_user_id INT NOT NULL,
                to_user_id INT NOT NULL,
                signal_type ENUM('offer','answer','ice','meta') NOT NULL,
                payload LONGTEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_call_signals_to (call_id, to_user_id, id),
                INDEX idx_call_signals_from (call_id, from_user_id, id),
                FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE,
                FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
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

    public function searchUsers() {
        Auth::requireAuth();
        $currentUserId = Auth::user()->id;
        $q = trim($_GET['q'] ?? '');
        if ($q === '') {
            $this->json(['users' => []]);
        }

        if (!preg_match('/^[A-Za-z0-9-]+$/', $q)) {
            $this->json([
                'error' => 'Use only letters, numbers, and dashes.',
                'users' => []
            ], 422);
        }

        $search = '%' . str_replace('-', '', $q) . '%';
        $results = Database::query("SELECT id, username, user_number, avatar_filename, presence_status, last_active_at FROM users WHERE id != ? AND (username LIKE ? OR user_number LIKE ?) LIMIT 10", [$currentUserId, '%' . $q . '%', $search])->fetchAll();

        foreach ($results as $r) {
            $r->formatted_user_number = User::formatUserNumber($r->user_number);
            $r->avatar_url = User::avatarUrl($r);
            User::attachEffectiveStatus($r);

            $r->friendship_status = null;
            $r->friendship_direction = null;
            $r->personal_chat_number = null;

            $friendship = Database::query(
                "SELECT user_id, friend_id, status
                 FROM friends
                 WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
                 LIMIT 1",
                [$currentUserId, $r->id, $r->id, $currentUserId]
            )->fetch();

            if ($friendship) {
                $r->friendship_status = $friendship->status ?? null;
                $r->friendship_direction = ((int)$friendship->user_id === (int)$currentUserId) ? 'outgoing' : 'incoming';

                if (($r->friendship_status ?? null) === 'accepted') {
                    $chat = Chat::getOrCreatePersonalChat($currentUserId, $currentUserId, (int)$r->id);
                    if ($chat) {
                        $r->personal_chat_number = User::formatUserNumber($chat->chat_number);
                    }
                }
            }
        }

        $this->json(['users' => $results]);
    }

    public function getFriends() {
        Auth::requireAuth();
        $userId = Auth::user()->id;

        $friends = Database::query("SELECT u.id, u.username, u.user_number, u.avatar_filename, u.presence_status, u.last_active_at, u.created_at FROM friends f JOIN users u ON (CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END) = u.id WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted' ORDER BY u.username ASC", [$userId, $userId, $userId])->fetchAll();
        foreach ($friends as $friend) {
            $friend->formatted_user_number = User::formatUserNumber($friend->user_number);
            $friend->avatar_url = User::avatarUrl($friend);
            User::attachEffectiveStatus($friend);
        }

        $this->json(['friends' => $friends]);
    }

    public function getChats() {
        Auth::requireAuth();
        $userId = Auth::user()->id;
        $supportsLastSeen = $this->supportsLastSeenMessageId();
        $supportsChatTitle = $this->supportsChatTitle();

        $favoriteRows = Database::query(
            "SELECT favorite_user_id FROM friend_favorites WHERE user_id = ?",
            [$userId]
        )->fetchAll();
        $favoriteUserIds = [];
        foreach ($favoriteRows as $favoriteRow) {
            $favoriteUserIds[(int)$favoriteRow->favorite_user_id] = true;
        }

        $query = "SELECT c.id,
                         c.chat_number,
                         c.type,
                 " . ($supportsChatTitle ? "c.title AS custom_title," : "NULL AS custom_title,") . "
                         c.created_at,
                         (SELECT m.content FROM messages m WHERE m.chat_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message,
                         (SELECT m.created_at FROM messages m WHERE m.chat_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message_at,";

        if ($supportsLastSeen) {
            $query .= "
                         (SELECT COUNT(*)
                          FROM messages mu
                          WHERE mu.chat_id = c.id
                                                        AND mu.user_id != cm.user_id
                            AND mu.id > COALESCE(cm.last_seen_message_id, 0)) AS unread_count,";
        } else {
            $query .= "
                         0 AS unread_count,";
        }

        $query .= "
                         (SELECT u.id
                          FROM chat_members cm2
                          JOIN users u ON u.id = cm2.user_id
                          WHERE cm2.chat_id = c.id AND cm2.user_id != ?
                          ORDER BY cm2.joined_at ASC
                          LIMIT 1) AS other_user_id,
                         (SELECT u.username
                          FROM chat_members cm2
                          JOIN users u ON u.id = cm2.user_id
                          WHERE cm2.chat_id = c.id AND cm2.user_id != ?
                          ORDER BY cm2.joined_at ASC
                          LIMIT 1) AS other_username,
                         (SELECT u.presence_status
                          FROM chat_members cm2
                          JOIN users u ON u.id = cm2.user_id
                          WHERE cm2.chat_id = c.id AND cm2.user_id != ?
                          ORDER BY cm2.joined_at ASC
                          LIMIT 1) AS other_presence_status,
                         (SELECT u.last_active_at
                          FROM chat_members cm2
                          JOIN users u ON u.id = cm2.user_id
                          WHERE cm2.chat_id = c.id AND cm2.user_id != ?
                          ORDER BY cm2.joined_at ASC
                          LIMIT 1) AS other_last_active_at
                  FROM chats c
                  JOIN chat_members cm ON cm.chat_id = c.id
                  WHERE cm.user_id = ?";

        if ($this->supportsChatSoftDelete()) {
            $query .= "
                    AND c.deleted_at IS NULL";
        }

        $query .= "
                  ORDER BY COALESCE(last_message_at, c.created_at) DESC";

        $chats = Database::query(
            $query,
            [$userId, $userId, $userId, $userId, $userId]
        )->fetchAll();

        foreach ($chats as $chat) {
            $chat->type = Chat::normalizeType($chat->type ?? null);
            $chat->is_favorite = false;
            $chat->unread_count = max(0, (int)($chat->unread_count ?? 0));
            $chat->chat_number_formatted = User::formatUserNumber($chat->chat_number);
            $chat->has_custom_title = false;
            if ($chat->type === 'group') {
                $customTitle = trim((string)($chat->custom_title ?? ''));
                if ($customTitle !== '') {
                    $chat->chat_title = $customTitle;
                    $chat->has_custom_title = true;
                } else {
                    $chat->chat_title = $chat->chat_number_formatted;
                }
                continue;
            }

            $chat->effective_status = 'offline';
            $chat->effective_status_label = 'Offline';
            $chat->effective_status_text_class = 'text-red-400';
            $chat->effective_status_dot_class = 'bg-red-500';

            if ((int)($chat->other_user_id ?? 0) > 0) {
                $otherUser = (object)[
                    'id' => (int)$chat->other_user_id,
                    'presence_status' => $chat->other_presence_status ?? null,
                    'last_active_at' => $chat->other_last_active_at ?? null
                ];
                User::attachEffectiveStatus($otherUser);

                $chat->is_favorite = isset($favoriteUserIds[(int)$otherUser->id]);

                $chat->effective_status = $otherUser->effective_status;
                $chat->effective_status_label = $otherUser->effective_status_label;
                $chat->effective_status_text_class = $otherUser->effective_status_text_class;
                $chat->effective_status_dot_class = $otherUser->effective_status_dot_class;
            }

            if (!empty($chat->other_username)) {
                $chat->chat_title = $chat->other_username;
            } else {
                $chat->chat_title = 'Personal Chat ' . User::formatUserNumber($chat->chat_number);
            }
        }

        $this->json(['chats' => $chats]);
    }

    public function getMessages($params) {
        Auth::requireAuth();
        $chatId = (int)($params['chat_id'] ?? 0);
        $userId = Auth::user()->id;
        $supportsLastSeen = $this->supportsLastSeenMessageId();

        $allowed = Database::query("SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?", [$chatId, $userId])->fetch();
        if (!$allowed) {
            $this->json(['error' => 'Access denied'], 403);
        }

        if ($this->supportsChatSoftDelete()) {
            $chat = Database::query("SELECT id, deleted_at FROM chats WHERE id = ?", [$chatId])->fetch();
            if (!$chat || $this->chatIsSoftDeleted($chat)) {
                $this->json(['error' => 'Chat not found'], 404);
            }
        }

        try {
            $messages = Database::query(
                "SELECT m.id, m.chat_id, m.user_id, m.content, m.created_at,
                        m.quoted_message_id, m.quoted_user_id, m.quoted_content,
                        u.username, u.user_number, u.avatar_filename, u.presence_status, u.last_active_at,
                        qu.username AS quoted_username, qu.user_number AS quoted_user_number
                 FROM messages m
                 JOIN users u ON u.id = m.user_id
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
                        u.username, u.user_number, u.presence_status, u.last_active_at,
                        qu.username AS quoted_username, qu.user_number AS quoted_user_number
                 FROM messages m
                 JOIN users u ON u.id = m.user_id
                 LEFT JOIN users qu ON qu.id = m.quoted_user_id
                 WHERE m.chat_id = ?
                 ORDER BY m.created_at ASC
                 LIMIT 200",
                [$chatId]
            )->fetchAll();
        }
        foreach ($messages as $message) {
            $message->avatar_url = User::avatarUrl($message);
            User::attachEffectiveStatus($message);
        }
        Message::attachMentionMaps($messages);
        Message::attachQuoteMentionMaps($messages);
        Message::attachReactions($messages, (int)$userId);
        Attachment::attachSubmittedToMessages($messages);

        if ($this->supportsSystemEvents()) {
            $chatRow = Database::query("SELECT type FROM chats WHERE id = ?", [$chatId])->fetch();
            if ($chatRow && Chat::isGroupType($chatRow->type ?? null)) {
                $systemEvents = Database::query(
                    "SELECT id, chat_id, event_type, content, created_at, 1 AS is_system_event FROM chat_system_events WHERE chat_id = ?",
                    [$chatId]
                )->fetchAll();

                $combined = array_merge($messages, $systemEvents);
                usort($combined, function ($a, $b) {
                    return strcmp($a->created_at, $b->created_at);
                });
                $messages = array_values(array_slice($combined, -200));
            }
        }

        try {
            $typingUsers = Database::query(
                "SELECT u.id, u.username, u.avatar_filename
                 FROM chat_typing_status cts
                 JOIN users u ON u.id = cts.user_id
                 WHERE cts.chat_id = ?
                   AND cts.user_id != ?
                   AND cts.updated_at >= NOW() - INTERVAL 8 SECOND
                 ORDER BY cts.updated_at DESC",
                [$chatId, $userId]
            )->fetchAll();
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if (stripos($message, 'chat_typing_status') === false && stripos($message, 'avatar_filename') === false) {
                throw $e;
            }

            $typingUsers = [];
        }
        foreach ($typingUsers as $typingUser) {
            $typingUser->avatar_url = User::avatarUrl($typingUser);
        }

        if ($supportsLastSeen) {
            $latestMessageId = !empty($messages) ? (int)($messages[count($messages) - 1]->id ?? 0) : 0;
            if ($latestMessageId > 0) {
                Database::query(
                    "UPDATE chat_members
                     SET last_seen_message_id = CASE
                         WHEN last_seen_message_id IS NULL OR last_seen_message_id < ? THEN ?
                         ELSE last_seen_message_id
                     END
                     WHERE chat_id = ? AND user_id = ?",
                    [$latestMessageId, $latestMessageId, $chatId, $userId]
                );
            }
        }

        $this->json(['messages' => $messages, 'typing_users' => $typingUsers]);
    }

    public function updateTyping() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $chatId = (int)($_POST['chat_id'] ?? 0);
        $isTyping = (int)($_POST['is_typing'] ?? 0) === 1;
        $userId = Auth::user()->id;

        if ($chatId <= 0) {
            $this->json(['error' => 'Invalid payload'], 400);
        }

        $allowed = Database::query("SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?", [$chatId, $userId])->fetch();
        if (!$allowed) {
            $this->json(['error' => 'Access denied'], 403);
        }

        try {
            if ($isTyping) {
                Database::query(
                    "INSERT INTO chat_typing_status (chat_id, user_id) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP",
                    [$chatId, $userId]
                );
            } else {
                Database::query("DELETE FROM chat_typing_status WHERE chat_id = ? AND user_id = ?", [$chatId, $userId]);
            }
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'chat_typing_status') === false) {
                throw $e;
            }
        }

        $this->json(['success' => true]);
    }

    public function getNotifications() {
        Auth::requireAuth();
        $userId = Auth::user()->id;
        $notifs = Database::query("SELECT id, type, title, message, link, `read`, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50", [$userId])->fetchAll();
        $this->json(['notifications' => $notifs]);
    }

    public function getInvites() {
        Auth::requireAuth();
        $invites = Database::query("SELECT ic.code, ic.used_by, u.username AS used_by_username, ic.used_at, ic.created_at FROM invite_codes ic LEFT JOIN users u ON u.id = ic.used_by WHERE ic.creator_id = ? ORDER BY ic.created_at DESC", [Auth::user()->id])->fetchAll();
        $this->json(['invites' => $invites]);
    }

    public function getActiveCall($params) {
        Auth::requireAuth();
        $chatId = (int)($params['chat_id'] ?? 0);

        $allowed = Database::query("SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?", [$chatId, Auth::user()->id])->fetch();
        if (!$allowed) {
            $this->json(['error' => 'Access denied'], 403);
        }

                $call = Database::query(
                        "SELECT c.id,
                                        c.chat_id,
                                        c.started_by,
                                        c.status,
                                        c.started_at,
                                        (SELECT COUNT(DISTINCT cp.user_id)
                                         FROM call_participants cp
                                         WHERE cp.call_id = c.id
                                             AND cp.left_at IS NULL) AS participant_count,
                                        (SELECT COUNT(*)
                                         FROM call_participants cp_self
                                         WHERE cp_self.call_id = c.id
                                             AND cp_self.user_id = ?
                                             AND cp_self.left_at IS NULL) AS current_user_joined
                         FROM calls c
                         WHERE c.chat_id = ?
                             AND c.status = 'active'
                         ORDER BY c.started_at DESC
                         LIMIT 1",
                        [Auth::user()->id, $chatId]
                )->fetch();
        $this->json(['call' => $call ?: null]);
    }

        public function getCurrentActiveCall() {
                Auth::requireAuth();
                $userId = (int)Auth::user()->id;

                $call = Database::query(
                        "SELECT c.id,
                                        c.chat_id,
                                        c.started_by,
                                        c.status,
                                        c.started_at,
                                        ch.chat_number,
                                        ch.type AS chat_type,
                                        (SELECT COUNT(DISTINCT cp.user_id)
                                         FROM call_participants cp
                                         WHERE cp.call_id = c.id
                                             AND cp.left_at IS NULL) AS participant_count,
                                        (SELECT COUNT(*)
                                         FROM call_participants cp_self
                                         WHERE cp_self.call_id = c.id
                                             AND cp_self.user_id = ?
                                             AND cp_self.left_at IS NULL) AS current_user_joined
                         FROM calls c
                         JOIN chats ch ON ch.id = c.chat_id
                         WHERE c.status = 'active'
                             AND EXISTS (
                                     SELECT 1
                                     FROM chat_members cm
                                     WHERE cm.chat_id = c.chat_id
                                         AND cm.user_id = ?
                             )
                         ORDER BY current_user_joined DESC, c.started_at DESC
                         LIMIT 1",
                        [$userId, $userId]
                )->fetch();

                $this->json(['call' => $call ?: null]);
        }

    public function getCallSignal($params) {
        Auth::requireAuth();
        $this->ensureCallSignalTableExists();

        $callId = (int)($params['call_id'] ?? 0);
        $userId = Auth::user()->id;
        $sinceId = (int)($_GET['since_id'] ?? 0);

        $call = Database::query("SELECT id, chat_id FROM calls WHERE id = ? AND status = 'active'", [$callId])->fetch();
        if (!$call) {
            $this->json(['error' => 'Call not found'], 404);
        }

        $member = Database::query("SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?", [$call->chat_id, $userId])->fetch();
        if (!$member) {
            $this->json(['error' => 'Access denied'], 403);
        }

        try {
            $participants = Database::query(
                "SELECT cp.user_id,
                        cp.screen_sharing,
                        cp.joined_at,
                        u.username
                 FROM call_participants cp
                 JOIN users u ON u.id = cp.user_id
                 WHERE cp.call_id = ?
                   AND cp.user_id != ?
                   AND cp.left_at IS NULL
                 ORDER BY cp.joined_at ASC",
                [$callId, $userId]
            )->fetchAll();
        } catch (Throwable $e) {
            $participants = Database::query(
                "SELECT cp.user_id,
                        cp.joined_at,
                        u.username
                 FROM call_participants cp
                 JOIN users u ON u.id = cp.user_id
                 WHERE cp.call_id = ?
                   AND cp.user_id != ?
                   AND cp.left_at IS NULL
                 ORDER BY cp.joined_at ASC",
                [$callId, $userId]
            )->fetchAll();
        }

        $signals = Database::query(
            "SELECT id, from_user_id, signal_type, payload, created_at
             FROM call_signals
             WHERE call_id = ?
               AND to_user_id = ?
               AND id > ?
             ORDER BY id ASC
             LIMIT 500",
            [$callId, $userId, $sinceId]
        )->fetchAll();

        $nextSinceId = $sinceId;
        $normalizedSignals = [];
        foreach ($signals as $signal) {
            $id = (int)($signal->id ?? 0);
            if ($id > $nextSinceId) {
                $nextSinceId = $id;
            }

            $normalizedSignals[] = [
                'id' => $id,
                'from_user_id' => (int)($signal->from_user_id ?? 0),
                'type' => (string)($signal->signal_type ?? ''),
                'payload' => (string)($signal->payload ?? ''),
                'created_at' => $signal->created_at ?? null,
            ];
        }

        $normalizedParticipants = array_map(static function ($participant) {
            return [
                'user_id' => (int)($participant->user_id ?? 0),
                'username' => (string)($participant->username ?? ''),
                'screen_sharing' => (bool)($participant->screen_sharing ?? false),
                'joined_at' => $participant->joined_at ?? null,
            ];
        }, $participants ?: []);

        $legacyPeer = null;
        foreach ($normalizedParticipants as $participant) {
            if ((int)($participant['user_id'] ?? 0) > 0) {
                $legacyPeer = [
                    'offer' => null,
                    'answer' => null,
                    'ice_candidates' => [],
                    'screen_sharing' => (bool)($participant['screen_sharing'] ?? false),
                    'username' => $participant['username'] ?? null,
                ];
                break;
            }
        }

        $this->json([
            'peer' => $legacyPeer ?: [
                'offer' => null,
                'answer' => null,
                'ice_candidates' => [],
                'screen_sharing' => false,
                'username' => null,
            ],
            'participants' => $normalizedParticipants,
            'signals' => $normalizedSignals,
            'next_since_id' => $nextSinceId,
        ]);
    }

    public function getServers() {
        Auth::requireAuth();
        $servers = Database::query("SELECT s.id, s.server_number, s.name, s.created_at FROM servers s JOIN server_members sm ON sm.server_id = s.id WHERE sm.user_id = ? ORDER BY s.name ASC", [Auth::user()->id])->fetchAll();
        $this->json(['servers' => $servers, 'coming_soon' => true]);
    }

    public function getChannels($params) {
        Auth::requireAuth();
        $serverId = (int)($params['server_id'] ?? 0);

        $allowed = Database::query("SELECT id FROM server_members WHERE server_id = ? AND user_id = ?", [$serverId, Auth::user()->id])->fetch();
        if (!$allowed) {
            $this->json(['error' => 'Access denied'], 403);
        }

        $channels = Database::query("SELECT id, channel_number, name, type, created_at FROM channels WHERE server_id = ? ORDER BY name ASC", [$serverId])->fetchAll();
        $this->json(['channels' => $channels, 'coming_soon' => true]);
    }

    public function updateStatus() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $status = User::normalizePresenceStatus($_POST['status'] ?? null);
        if ($status === null) {
            $this->json(['error' => 'Invalid status'], 400);
        }

        $userId = (int)Auth::user()->id;
        User::setPresenceStatus($userId, $status);

        $user = User::find($userId);
        User::attachEffectiveStatus($user);

        $this->json([
            'success' => true,
            'selected_status' => $status,
            'effective_status' => $user->effective_status,
            'effective_status_label' => $user->effective_status_label,
            'effective_status_text_class' => $user->effective_status_text_class,
            'effective_status_dot_class' => $user->effective_status_dot_class
        ]);
    }
}