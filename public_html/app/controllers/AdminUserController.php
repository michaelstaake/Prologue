<?php
class AdminUserController extends Controller {
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
            User::deleteAllSessionsForUser((int)$targetUserId);

            Database::query("DELETE FROM reports WHERE reporter_id = ?", [(int)$targetUserId]);
            Database::query("DELETE FROM call_participants WHERE user_id = ?", [(int)$targetUserId]);
            Database::query("DELETE FROM calls WHERE started_by = ?", [(int)$targetUserId]);
            Database::query(
                "DELETE FROM calls WHERE chat_id IN (SELECT id FROM chats WHERE created_by = ?)",
                [(int)$targetUserId]
            );
            Database::query("DELETE FROM messages WHERE user_id = ?", [(int)$targetUserId]);
            Database::query("DELETE FROM chats WHERE created_by = ?", [(int)$targetUserId]);

            Database::query("DELETE FROM users WHERE id = ?", [(int)$targetUserId]);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        $this->json(['success' => true]);
    }
}
