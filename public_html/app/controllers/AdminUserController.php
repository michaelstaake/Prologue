<?php
class AdminUserController extends Controller {
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

    private function generateUniqueUserNumber(): string {
        do {
            $number = str_pad((string) random_int(0, 9999999999999999), 16, '0', STR_PAD_LEFT);
            $exists = (int)Database::query(
                "SELECT COUNT(*) FROM users WHERE user_number = ?",
                [$number]
            )->fetchColumn() > 0;
        } while ($exists);

        return $number;
    }

    private function createRetainedMessagesUserForDeletedUser(int $targetUserId): int {
        $targetUserId = (int)$targetUserId;
        if ($targetUserId <= 0) {
            return 0;
        }

        $retainedEmail = 'deleted-retained-' . $targetUserId . '@prologue.local.invalid';
        $existing = Database::query(
            "SELECT id FROM users WHERE email = ? LIMIT 1",
            [$retainedEmail]
        )->fetch();
        if ($existing && (int)($existing->id ?? 0) > 0) {
            return (int)$existing->id;
        }

        $usernameBase = 'deletedretained' . $targetUserId;
        $username = substr($usernameBase, 0, 32);
        $suffix = 1;
        while (Database::query("SELECT id FROM users WHERE username = ? LIMIT 1", [$username])->fetch()) {
            $suffixToken = (string)$suffix;
            $allowedBaseLength = max(1, 32 - strlen($suffixToken));
            $username = substr($usernameBase, 0, $allowedBaseLength) . $suffixToken;
            $suffix++;
        }

        $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $userNumber = $this->generateUniqueUserNumber();

        Database::query(
            "INSERT INTO users (username, email, password, user_number, presence_status, is_banned, role, email_verified_at)
             VALUES (?, ?, ?, ?, 'offline', 1, 'user', NOW())",
            [$username, $retainedEmail, $passwordHash, $userNumber]
        );

        return (int)Database::getInstance()->lastInsertId();
    }

    private function getPersonalChatsForDeletedUser(int $targetUserId): array {
        return Database::query(
            "SELECT c.id,
                    c.chat_number,
                    (SELECT cm2.user_id
                     FROM chat_members cm2
                     WHERE cm2.chat_id = c.id AND cm2.user_id != ?
                     ORDER BY cm2.joined_at ASC
                     LIMIT 1) AS other_user_id
             FROM chats c
             JOIN chat_members cm ON cm.chat_id = c.id
             WHERE cm.user_id = ?
               AND c.type IN ('personal', 'dm')",
            [$targetUserId, $targetUserId]
        )->fetchAll();
    }

    private function addDeletedUserNoticeToPersonalChat(int $chatId, string $chatNumber, int $fallbackUserId): void {
        $formattedChatNumber = User::formatUserNumber($chatNumber);
        $deleteUrl = '/c/' . $formattedChatNumber . '/delete';
        $notice = 'User deleted. ' . $deleteUrl;

        if ($this->supportsSystemEvents()) {
            Database::query(
                "INSERT INTO chat_system_events (chat_id, event_type, content) VALUES (?, 'user_deleted', ?)",
                [$chatId, $notice]
            );
            return;
        }

        if ($fallbackUserId > 0) {
            Database::query(
                "INSERT INTO messages (chat_id, user_id, content) VALUES (?, ?, ?)",
                [$chatId, $fallbackUserId, $notice]
            );
        }
    }

    private function transferChatOwnershipForDeletedUser(int $targetUserId, int $fallbackOwnerUserId, bool $includeGroupChats): void {
        $chatTypeFilter = $includeGroupChats
            ? "type IN ('personal', 'dm', 'group')"
            : "type IN ('personal', 'dm')";

        $chats = Database::query(
            "SELECT id
             FROM chats
             WHERE created_by = ?
               AND " . $chatTypeFilter,
            [$targetUserId]
        )->fetchAll();

        foreach ($chats as $chat) {
            $chatId = (int)($chat->id ?? 0);
            if ($chatId <= 0) {
                continue;
            }

            $nextOwnerId = (int)Database::query(
                "SELECT cm.user_id
                 FROM chat_members cm
                 WHERE cm.chat_id = ?
                   AND cm.user_id != ?
                 ORDER BY cm.joined_at ASC
                 LIMIT 1",
                [$chatId, $targetUserId]
            )->fetchColumn();

            if ($nextOwnerId <= 0) {
                $nextOwnerId = $fallbackOwnerUserId;
            }

            if ($nextOwnerId > 0 && $nextOwnerId !== $targetUserId) {
                Database::query("UPDATE chats SET created_by = ? WHERE id = ?", [$nextOwnerId, $chatId]);
            }
        }
    }

    private function requireAdminUser() {
        Auth::requireAuth();
        $user = Auth::user();
        if (!$user || strtolower((string)($user->role ?? '')) !== 'admin') {
            ErrorHandler::abort(403, 'Access denied');
        }

        return $user;
    }

    private function findTargetUser($targetUserId) {
        if ($targetUserId <= 0) {
            return null;
        }

        return Database::query(
            "SELECT id, username, role, is_banned FROM users WHERE id = ? LIMIT 1",
            [(int)$targetUserId]
        )->fetch();
    }

    public function index() {
        $adminUser = $this->requireAdminUser();

        $users = Database::query(
            "SELECT id, username, user_number, avatar_filename, presence_status, last_active_at, role, is_banned, created_at
             FROM users
             WHERE id != ?
             ORDER BY username ASC",
            [(int)$adminUser->id]
        )->fetchAll();

        User::attachEffectiveStatusList($users);

        $this->view('users', [
            'user' => $adminUser,
            'users' => $users,
            'csrf' => $this->csrfToken()
        ]);
    }

    public function changeGroup() {
        $adminUser = $this->requireAdminUser();
        Auth::csrfValidate();

        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $targetRole = strtolower(trim((string)($_POST['role'] ?? '')));
        if (!in_array($targetRole, ['user', 'admin'], true)) {
            $this->json(['error' => 'Invalid role'], 400);
        }

        if ($targetUserId <= 0 || $targetUserId === (int)$adminUser->id) {
            $this->json(['error' => 'Invalid user'], 400);
        }

        $targetUser = $this->findTargetUser($targetUserId);
        if (!$targetUser) {
            $this->json(['error' => 'User not found'], 404);
        }

        Database::query(
            "UPDATE users SET role = ? WHERE id = ?",
            [$targetRole, (int)$targetUserId]
        );

        $this->json(['success' => true, 'role' => $targetRole]);
    }

    public function ban() {
        $adminUser = $this->requireAdminUser();
        Auth::csrfValidate();

        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $targetIsBanned = (int)($_POST['is_banned'] ?? 1) === 1 ? 1 : 0;
        if ($targetUserId <= 0 || $targetUserId === (int)$adminUser->id) {
            $this->json(['error' => 'Invalid user'], 400);
        }

        $targetUser = $this->findTargetUser($targetUserId);
        if (!$targetUser) {
            $this->json(['error' => 'User not found'], 404);
        }

        if ($targetIsBanned === 1) {
            Database::query(
                "UPDATE users SET is_banned = 1, last_active_at = NULL WHERE id = ?",
                [(int)$targetUserId]
            );
            User::deleteAllSessionsForUser((int)$targetUserId);
        } else {
            Database::query(
                "UPDATE users SET is_banned = 0 WHERE id = ?",
                [(int)$targetUserId]
            );
        }

        $this->json(['success' => true, 'is_banned' => $targetIsBanned]);
    }

    public function delete() {
        $adminUser = $this->requireAdminUser();
        Auth::csrfValidate();

        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $retainMessages = (int)($_POST['retain_messages'] ?? 0) === 1;
        if ($targetUserId <= 0 || $targetUserId === (int)$adminUser->id) {
            $this->json(['error' => 'Invalid user'], 400);
        }

        $targetUser = $this->findTargetUser($targetUserId);
        if (!$targetUser) {
            $this->json(['error' => 'User not found'], 404);
        }

        $pdo = Database::getInstance();
        $pdo->beginTransaction();

        try {
            $targetUsername = (string)($targetUser->username ?? 'deleted-user');

            $personalChats = $this->getPersonalChatsForDeletedUser((int)$targetUserId);
            if ($this->supportsChatTitle()) {
                foreach ($personalChats as $personalChat) {
                    $chatId = (int)($personalChat->id ?? 0);
                    if ($chatId <= 0) {
                        continue;
                    }

                    Database::query("UPDATE chats SET title = ? WHERE id = ?", [$targetUsername, $chatId]);
                }
            }

            foreach ($personalChats as $personalChat) {
                $chatId = (int)($personalChat->id ?? 0);
                if ($chatId <= 0) {
                    continue;
                }

                $chatNumber = (string)($personalChat->chat_number ?? '');
                $fallbackUserId = (int)($personalChat->other_user_id ?? 0);
                if ($chatNumber !== '' && preg_match('/^\d{16}$/', $chatNumber)) {
                    $this->addDeletedUserNoticeToPersonalChat($chatId, $chatNumber, $fallbackUserId);
                }
            }

            $this->transferChatOwnershipForDeletedUser((int)$targetUserId, (int)$adminUser->id, $retainMessages);

            User::deleteAllSessionsForUser((int)$targetUserId);

            Database::query("DELETE FROM reports WHERE reporter_id = ?", [(int)$targetUserId]);
            Database::query("DELETE FROM call_participants WHERE user_id = ?", [(int)$targetUserId]);

            $retainedMessagesUserId = 0;
            if ($retainMessages) {
                $retainedMessagesUserId = $this->createRetainedMessagesUserForDeletedUser((int)$targetUserId);

                Database::query(
                    "UPDATE messages SET user_id = ? WHERE user_id = ?",
                    [$retainedMessagesUserId, (int)$targetUserId]
                );
                Database::query(
                    "UPDATE calls SET started_by = ? WHERE started_by = ?",
                    [$retainedMessagesUserId, (int)$targetUserId]
                );
            } else {
                Database::query("DELETE FROM calls WHERE started_by = ?", [(int)$targetUserId]);
                Database::query(
                    "DELETE FROM calls WHERE chat_id IN (SELECT id FROM chats WHERE created_by = ? AND type = 'group')",
                    [(int)$targetUserId]
                );
                Database::query("DELETE FROM messages WHERE user_id = ?", [(int)$targetUserId]);
                Database::query("DELETE FROM chats WHERE created_by = ? AND type = 'group'", [(int)$targetUserId]);
            }

            Database::query("DELETE FROM users WHERE id = ?", [(int)$targetUserId]);

            if ($retainMessages && $retainedMessagesUserId > 0 && $targetUsername !== '') {
                try {
                    Database::query(
                        "UPDATE users SET username = ? WHERE id = ?",
                        [$targetUsername, $retainedMessagesUserId]
                    );
                } catch (Throwable $renameException) {
                }
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        $this->json(['success' => true, 'retain_messages' => $retainMessages]);
    }
}
