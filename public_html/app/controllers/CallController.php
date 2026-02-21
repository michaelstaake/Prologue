<?php
class CallController extends Controller {
    private const CALL_USER_LIMIT = 20;
    private const USER_LIMIT_REACHED_MESSAGE = 'User limit reached. Please try again later.';

    private function getActiveParticipantCount(int $callId): int {
        return (int)Database::query(
            "SELECT COUNT(DISTINCT user_id)
             FROM call_participants
             WHERE call_id = ?
               AND left_at IS NULL",
            [$callId]
        )->fetchColumn();
    }

    private function isUserActivelyInCall(int $callId, int $userId): bool {
        $count = (int)Database::query(
            "SELECT COUNT(*)
             FROM call_participants
             WHERE call_id = ?
               AND user_id = ?
               AND left_at IS NULL",
            [$callId, $userId]
        )->fetchColumn();

        return $count > 0;
    }

    private function enforceCallCapacityOrFail(int $callId, int $userId): void {
        if ($this->isUserActivelyInCall($callId, $userId)) {
            return;
        }

        if ($this->getActiveParticipantCount($callId) >= self::CALL_USER_LIMIT) {
            $this->json(['error' => self::USER_LIMIT_REACHED_MESSAGE], 429);
        }
    }

    private function clearCallNotificationsForUser(int $userId, string $chatNumber): void {
        $callLink = '/c/' . User::formatUserNumber($chatNumber);
        Database::query(
            "DELETE FROM notifications WHERE user_id = ? AND type = 'call' AND link = ?",
            [$userId, $callLink]
        );
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

    private function upsertActiveParticipant(int $callId, int $userId, int $video = 0): void {
        $active = Database::query(
            "SELECT id
             FROM call_participants
             WHERE call_id = ?
               AND user_id = ?
               AND left_at IS NULL
             ORDER BY id DESC
             LIMIT 1",
            [$callId, $userId]
        )->fetch();

        if ($active) {
            $activeId = (int)$active->id;
            Database::query(
                "UPDATE call_participants
                 SET joined_at = CURRENT_TIMESTAMP,
                     left_at = NULL,
                     video = ?
                 WHERE id = ?",
                [$video, $activeId]
            );

            Database::query(
                "UPDATE call_participants
                 SET left_at = COALESCE(left_at, NOW())
                 WHERE call_id = ?
                   AND user_id = ?
                   AND left_at IS NULL
                   AND id != ?",
                [$callId, $userId, $activeId]
            );
            return;
        }

        Database::query(
            "INSERT INTO call_participants (call_id, user_id, video) VALUES (?, ?, ?)",
            [$callId, $userId, $video]
        );
    }

    private function isPersonalChatPeerBanned(int $chatId, int $userId): bool {
        $chat = Database::query("SELECT id, type FROM chats WHERE id = ?", [$chatId])->fetch();
        if (!$chat || Chat::isGroupType($chat->type ?? null)) {
            return false;
        }

        $peer = Database::query(
            "SELECT u.is_banned
             FROM chat_members cm
             JOIN users u ON u.id = cm.user_id
             WHERE cm.chat_id = ? AND cm.user_id != ?
             LIMIT 1",
            [$chatId, $userId]
        )->fetch();

        return (int)($peer->is_banned ?? 0) === 1;
    }

    private function formatCallDuration(int $durationSeconds): string {
        $safeDuration = max(0, $durationSeconds);
        $minutes = intdiv($safeDuration, 60);
        $seconds = $safeDuration % 60;
        return $minutes . ':' . str_pad((string)$seconds, 2, '0', STR_PAD_LEFT);
    }

    private function appendCallHistoryMessage(int $callId): void {
        $call = Database::query(
            "SELECT c.id,
                    c.chat_id,
                    c.started_by,
                    c.started_at,
                    c.ended_at,
                    u.username AS starter_username,
                    (SELECT COUNT(DISTINCT cp.user_id)
                     FROM call_participants cp
                     WHERE cp.call_id = c.id) AS participant_total
             FROM calls c
             JOIN users u ON u.id = c.started_by
             WHERE c.id = ?",
            [$callId]
        )->fetch();

        if (!$call) {
            return;
        }

        $participantTotal = max(0, (int)($call->participant_total ?? 0));
        $durationSeconds = 0;

        if ($participantTotal > 1) {
            $startedAt = strtotime((string)($call->started_at ?? ''));
            $endedAt = strtotime((string)($call->ended_at ?? ''));
            if ($startedAt !== false && $endedAt !== false) {
                $durationSeconds = max(0, $endedAt - $startedAt);
            }
        }

        $durationLabel = $this->formatCallDuration($durationSeconds);
        $starterUsername = trim((string)($call->starter_username ?? 'Someone'));
        if ($starterUsername === '') {
            $starterUsername = 'Someone';
        }

        $content = $starterUsername . ' started a call (' . $durationLabel . ')';
        Database::query(
            "INSERT INTO messages (chat_id, user_id, content) VALUES (?, ?, ?)",
            [(int)$call->chat_id, (int)$call->started_by, $content]
        );
    }

    public function startCall() {
        Auth::requireAuth();
        Auth::csrfValidate();
        $chatId = (int)($_POST['chat_id'] ?? 0);
        $userId = Auth::user()->id;

        if ($chatId <= 0) {
            $this->json(['error' => 'Invalid chat'], 400);
        }

        $chat = Database::query("SELECT id, chat_number FROM chats WHERE id = ?", [$chatId])->fetch();
        if (!$chat) {
            $this->json(['error' => 'Chat not found'], 404);
        }

        $member = Database::query("SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?", [$chatId, $userId])->fetch();
        if (!$member) {
            $this->json(['error' => 'Access denied'], 403);
        }

        if ($this->isPersonalChatPeerBanned($chatId, (int)$userId)) {
            $this->json(['error' => "You can't call a banned user"], 403);
        }

        $activeCall = Database::query("SELECT id FROM calls WHERE chat_id = ? AND status = 'active'", [$chatId])->fetch();
        if ($activeCall) {
            $activeCallId = (int)$activeCall->id;
            $this->enforceCallCapacityOrFail($activeCallId, (int)$userId);
            $this->upsertActiveParticipant($activeCallId, (int)$userId, 0);

            $this->clearCallNotificationsForUser((int)$userId, (string)$chat->chat_number);

            $this->json(['call_id' => $activeCallId, 'joined_existing' => true]);
        }

        Database::query("INSERT INTO calls (chat_id, started_by) VALUES (?, ?)", [$chatId, $userId]);
        $callId = Database::getInstance()->lastInsertId();

        // Add starter as participant
        $this->upsertActiveParticipant((int)$callId, (int)$userId, 0);

        $members = Database::query("SELECT user_id FROM chat_members WHERE chat_id = ? AND user_id != ?", [$chatId, $userId])->fetchAll();
        foreach ($members as $m) {
            Notification::create($m->user_id, 'call', 'Incoming Call', Auth::user()->username . ' started a call', '/c/' . User::formatUserNumber($chat->chat_number));
        }

        $this->json(['call_id' => $callId, 'joined_existing' => false]);
    }

    public function signal() {
        Auth::requireAuth();
        Auth::csrfValidate();
        $this->ensureCallSignalTableExists();

        $callId = (int)($_POST['call_id'] ?? 0);
        $userId = Auth::user()->id;
        $type = $_POST['type'] ?? ''; // offer, answer, ice
        $data = $_POST['data'] ?? '';
        $toUserId = (int)($_POST['to_user_id'] ?? 0);

        $call = Database::query("SELECT id, chat_id FROM calls WHERE id = ? AND status = 'active'", [$callId])->fetch();
        if (!$call) {
            $this->json(['error' => 'Call not found'], 404);
        }

        $member = Database::query("SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?", [$call->chat_id, $userId])->fetch();
        if (!$member) {
            $this->json(['error' => 'Access denied'], 403);
        }

        $this->enforceCallCapacityOrFail((int)$callId, (int)$userId);

        $this->upsertActiveParticipant((int)$callId, (int)$userId, 0);

        if ($type === 'meta') {
            $meta = json_decode($data, true) ?: [];
            if (isset($meta['screen_sharing'])) {
                $screenSharing = (int)((bool)$meta['screen_sharing']);
                try {
                    Database::query("UPDATE call_participants SET screen_sharing = ? WHERE call_id = ? AND user_id = ?", [$screenSharing, $callId, $userId]);
                } catch (Throwable $e) {
                    // Column may not exist on older installs; ignore
                }
            }
        }

        if (!in_array($type, ['offer', 'answer', 'ice', 'meta'], true)) {
            $this->json(['error' => 'Invalid signal type'], 400);
        }

        if ($toUserId > 0) {
            $targetActive = Database::query(
                "SELECT id
                 FROM call_participants
                 WHERE call_id = ?
                   AND user_id = ?
                   AND left_at IS NULL
                 LIMIT 1",
                [$callId, $toUserId]
            )->fetch();

            if (!$targetActive) {
                $this->json(['error' => 'Target user is not in call'], 409);
            }

            Database::query(
                "INSERT INTO call_signals (call_id, from_user_id, to_user_id, signal_type, payload)
                 VALUES (?, ?, ?, ?, ?)",
                [$callId, $userId, $toUserId, $type, $data]
            );
        }

        $this->json(['success' => true]);
    }

    public function declineCall() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $callId = (int)($_POST['call_id'] ?? 0);
        $user = Auth::user();
        $userId = (int)$user->id;

        if ($callId <= 0) {
            $this->json(['error' => 'Invalid call'], 400);
        }

        $call = Database::query(
            "SELECT c.id, c.chat_id, c.started_by, c.status, ch.chat_number
             FROM calls c
             JOIN chats ch ON ch.id = c.chat_id
             WHERE c.id = ?",
            [$callId]
        )->fetch();
        if (!$call) {
            $this->json(['error' => 'Call not found'], 404);
        }

        $member = Database::query(
            "SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?",
            [(int)$call->chat_id, $userId]
        )->fetch();
        if (!$member) {
            $this->json(['error' => 'Access denied'], 403);
        }

        $this->upsertActiveParticipant((int)$callId, $userId, 0);

        Database::query(
            "UPDATE call_participants
             SET left_at = COALESCE(left_at, NOW())
             WHERE call_id = ?
               AND user_id = ?",
            [$callId, $userId]
        );

        $chatNumber = (string)$call->chat_number;
        $this->clearCallNotificationsForUser($userId, $chatNumber);

        $notifiedCaller = false;
        if ((int)$call->started_by > 0 && (int)$call->started_by !== $userId && (string)($call->status ?? '') === 'active') {
            Notification::create(
                (int)$call->started_by,
                'call',
                'Call Declined',
                (string)$user->username . ' declined your call',
                '/c/' . User::formatUserNumber($chatNumber)
            );
            $notifiedCaller = true;
        }

        $this->json(['success' => true, 'notified_caller' => $notifiedCaller]);
    }

    public function endCall() {
        Auth::requireAuth();
        Auth::csrfValidate();
        $callId = (int)($_POST['call_id'] ?? 0);
        $userId = Auth::user()->id;

        $call = Database::query("SELECT c.id, c.chat_id, c.status FROM calls c WHERE c.id = ?", [$callId])->fetch();
        if (!$call) {
            $this->json(['error' => 'Call not found'], 404);
        }

        $member = Database::query("SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?", [$call->chat_id, $userId])->fetch();
        if (!$member) {
            $this->json(['error' => 'Access denied'], 403);
        }

        Database::query(
            "UPDATE call_participants
             SET left_at = COALESCE(left_at, NOW())
             WHERE call_id = ?
               AND user_id = ?",
            [$callId, $userId]
        );

        if ((string)($call->status ?? '') !== 'active') {
            $this->json(['success' => true, 'ended' => true]);
        }

        $remainingParticipants = (int)Database::query(
            "SELECT COUNT(DISTINCT user_id)
             FROM call_participants
             WHERE call_id = ?
               AND left_at IS NULL",
            [$callId]
        )->fetchColumn();

        $ended = false;
        if ($remainingParticipants <= 1) {
            Database::query(
                "UPDATE call_participants
                 SET left_at = COALESCE(left_at, NOW())
                 WHERE call_id = ?
                   AND left_at IS NULL",
                [$callId]
            );

            $endStatement = Database::query(
                "UPDATE calls
                 SET status = 'ended',
                     ended_at = COALESCE(ended_at, NOW())
                 WHERE id = ?
                   AND status = 'active'",
                [$callId]
            );

            if ($endStatement->rowCount() > 0) {
                $ended = true;
                $this->appendCallHistoryMessage($callId);
            }
        }

        $this->json(['success' => true, 'ended' => $ended]);
    }
}