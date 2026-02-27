<?php
class TrashController extends Controller {
    private function requireAdminUser() {
        Auth::requireAuth();
        $user = Auth::user();
        if (!$user || strtolower((string)($user->role ?? '')) !== 'admin') {
            ErrorHandler::abort(403, 'Access denied');
        }

        return $user;
    }

    private function softDeleteSupportedOrAbort(): void {
        if (!Chat::supportsSoftDelete()) {
            ErrorHandler::abort(400, 'Trash requires chat soft-delete database update');
        }
    }

    public function index() {
        $user = $this->requireAdminUser();
        $this->softDeleteSupportedOrAbort();

        $rows = Database::query(
            "SELECT c.id,
                    c.chat_number,
                    c.title,
                    c.deleted_at,
                    c.deleted_by,
                    du.username AS deleted_by_username,
                    (SELECT COUNT(*) FROM messages m WHERE m.chat_id = c.id) AS message_count,
                    (SELECT COUNT(*) FROM attachments a WHERE a.chat_id = c.id) AS attachment_count
             FROM chats c
             LEFT JOIN users du ON du.id = c.deleted_by
             WHERE c.type = 'group' AND c.deleted_at IS NOT NULL
             ORDER BY c.deleted_at DESC"
        )->fetchAll();

        foreach ($rows as $row) {
            $row->chat_number_formatted = User::formatUserNumber((string)$row->chat_number);
            $customTitle = trim((string)($row->title ?? ''));
            $row->chat_title = $customTitle !== '' ? $customTitle : $row->chat_number_formatted;
        }

        $toastMessage = '';
        $toastKind = 'info';
        $success = strtolower(trim((string)($_GET['success'] ?? '')));
        $error = strtolower(trim((string)($_GET['error'] ?? '')));

        if ($success === 'chat_permanently_deleted') {
            $toastMessage = 'Chat permanently deleted.';
            $toastKind = 'success';
        } elseif ($error === 'chat_not_found') {
            $toastMessage = 'Deleted chat not found.';
            $toastKind = 'error';
        } elseif ($error === 'chat_delete_failed') {
            $toastMessage = 'Unable to permanently delete chat.';
            $toastKind = 'error';
        }

        $this->view('trash', [
            'user' => $user,
            'deletedChats' => $rows,
            'toastMessage' => $toastMessage,
            'toastKind' => $toastKind,
            'csrf' => $this->csrfToken()
        ]);
    }

    public function show($params) {
        $user = $this->requireAdminUser();
        $this->softDeleteSupportedOrAbort();

        $chatNumber = (string)($params['chat_number'] ?? '');
        if (!preg_match('/^\d{4}-\d{4}-\d{4}-\d{4}$/', $chatNumber)) {
            $this->redirect('/trash?error=chat_not_found');
        }

        $rawChatNumber = str_replace('-', '', $chatNumber);
        $chat = Database::query(
            "SELECT c.id, c.chat_number, c.title, c.created_by, c.deleted_at, c.deleted_by,
                    creator.username AS created_by_username,
                    deleted_user.username AS deleted_by_username
             FROM chats c
             LEFT JOIN users creator ON creator.id = c.created_by
             LEFT JOIN users deleted_user ON deleted_user.id = c.deleted_by
             WHERE c.chat_number = ? AND c.type = 'group' AND c.deleted_at IS NOT NULL
             LIMIT 1",
            [$rawChatNumber]
        )->fetch();

        if (!$chat) {
            $this->redirect('/trash?error=chat_not_found');
        }

        $chat->chat_number_formatted = User::formatUserNumber((string)$chat->chat_number);
        $chatTitle = trim((string)($chat->title ?? ''));
        if ($chatTitle === '') {
            $chatTitle = $chat->chat_number_formatted;
        }

        try {
            $messages = Database::query(
                "SELECT m.id, m.chat_id, m.user_id, m.content, m.created_at,
                        m.quoted_message_id, m.quoted_user_id, m.quoted_content,
                        u.username, u.email AS user_email, u.user_number, u.avatar_filename, u.presence_status, u.last_active_at,
                        qu.username AS quoted_username, qu.user_number AS quoted_user_number
                 FROM messages m
                 JOIN users u ON u.id = m.user_id
                 LEFT JOIN users qu ON qu.id = m.quoted_user_id
                 WHERE m.chat_id = ?
                 ORDER BY m.created_at ASC
                 LIMIT 300",
                [(int)$chat->id]
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
                 JOIN users u ON u.id = m.user_id
                 LEFT JOIN users qu ON qu.id = m.quoted_user_id
                 WHERE m.chat_id = ?
                 ORDER BY m.created_at ASC
                 LIMIT 300",
                [(int)$chat->id]
            )->fetchAll();
        }

        foreach ($messages as $message) {
            $message->username = User::decorateDeletedRetainedUsername($message->username ?? '', $message->user_email ?? null);
            $message->avatar_url = User::avatarUrl($message);
            User::attachEffectiveStatus($message);
        }
        Message::attachMentionMaps($messages);
        Message::attachQuoteMentionMaps($messages);
        Attachment::attachSubmittedToMessages($messages);

        if (Database::query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_system_events'"
        )->fetchColumn() > 0) {
            $events = Database::query(
                "SELECT id, chat_id, event_type, content, created_at, 1 AS is_system_event
                 FROM chat_system_events
                 WHERE chat_id = ?
                 ORDER BY created_at ASC",
                [(int)$chat->id]
            )->fetchAll();

            $combined = array_merge($messages, $events);
            usort($combined, function ($a, $b) {
                return strcmp((string)$a->created_at, (string)$b->created_at);
            });
            $messages = array_values($combined);
        }

        $this->view('trash_chat', [
            'user' => $user,
            'chat' => $chat,
            'chatTitle' => $chatTitle,
            'messages' => $messages,
            'csrf' => $this->csrfToken()
        ]);
    }

    public function delete() {
        $this->requireAdminUser();
        $this->softDeleteSupportedOrAbort();
        Auth::csrfValidate();

        $chatId = (int)($_POST['chat_id'] ?? 0);
        if ($chatId <= 0) {
            $this->redirect('/trash?error=chat_not_found');
        }

        $chat = Database::query(
            "SELECT id FROM chats WHERE id = ? AND type = 'group' AND deleted_at IS NOT NULL LIMIT 1",
            [$chatId]
        )->fetch();
        if (!$chat) {
            $this->redirect('/trash?error=chat_not_found');
        }

        $attachmentsReleased = Attachment::deleteFilesForChatId($chatId);
        if (!$attachmentsReleased) {
            $this->redirect('/trash?error=chat_delete_failed');
        }

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();

            Database::query(
                "DELETE FROM call_participants
                 WHERE call_id IN (SELECT id FROM calls WHERE chat_id = ?)",
                [$chatId]
            );
            Database::query(
                "DELETE FROM call_signals
                 WHERE call_id IN (SELECT id FROM calls WHERE chat_id = ?)",
                [$chatId]
            );
            Database::query("DELETE FROM calls WHERE chat_id = ?", [$chatId]);
            Database::query("DELETE FROM chats WHERE id = ?", [$chatId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->redirect('/trash?error=chat_delete_failed');
        }

        $this->redirect('/trash?success=chat_permanently_deleted');
    }
}
