<?php
class ReportController extends Controller {
    private function requireAdminUser() {
        Auth::requireAuth();
        $user = Auth::user();
        if (!$user || strtolower((string)($user->role ?? '')) !== 'admin') {
            ErrorHandler::abort(403, 'Access denied');
        }

        return $user;
    }

    private function normalizeFilter($filter) {
        $value = strtolower(trim((string)$filter));
        if ($value === 'all') {
            return 'all';
        }

        if ($value === 'handled') {
            return 'handled';
        }

        return 'new';
    }

    public function index() {
        $user = $this->requireAdminUser();
        $filter = $this->normalizeFilter($_GET['filter'] ?? 'new');

        $whereSql = "WHERE r.status = 'pending'";
        if ($filter === 'handled') {
            $whereSql = "WHERE r.status <> 'pending'";
        } elseif ($filter === 'all') {
            $whereSql = '';
        }

        $reports = Database::query(
            "SELECT r.id,
                    r.reporter_id,
                    r.target_type,
                    r.target_id,
                    r.reason,
                    r.status,
                    r.created_at,
                    reporter.username AS reporter_username,
                    reporter.user_number AS reporter_user_number,
                    reporter.avatar_filename AS reporter_avatar_filename,
                    target_user.username AS target_user_username,
                    target_user.user_number AS target_user_number,
                    target_chat.chat_number AS target_chat_number,
                    target_message.chat_id AS target_message_chat_id,
                    message_chat.chat_number AS target_message_chat_number
             FROM reports r
             JOIN users reporter ON reporter.id = r.reporter_id
             LEFT JOIN users target_user ON r.target_type = 'user' AND target_user.id = r.target_id
             LEFT JOIN chats target_chat ON r.target_type = 'chat' AND target_chat.id = r.target_id
             LEFT JOIN messages target_message ON r.target_type = 'message' AND target_message.id = r.target_id
             LEFT JOIN chats message_chat ON message_chat.id = target_message.chat_id
             {$whereSql}
             ORDER BY r.created_at DESC",
            []
        )->fetchAll();

        $toastMessage = '';
        $toastKind = 'info';
        $success = strtolower(trim((string)($_GET['success'] ?? '')));
        $error = strtolower(trim((string)($_GET['error'] ?? '')));

        if ($success === 'report_handled') {
            $toastMessage = 'Report marked as handled.';
            $toastKind = 'success';
        } elseif ($error === 'invalid_report') {
            $toastMessage = 'Invalid report.';
            $toastKind = 'error';
        } elseif ($error === 'report_not_found') {
            $toastMessage = 'Report not found or already handled.';
            $toastKind = 'error';
        }

        $this->view('reports', [
            'user' => $user,
            'reports' => $reports,
            'selectedFilter' => $filter,
            'toastMessage' => $toastMessage,
            'toastKind' => $toastKind,
            'csrf' => $this->csrfToken()
        ]);
    }

    public function markHandled() {
        $this->requireAdminUser();
        Auth::csrfValidate();

        $reportId = (int)($_POST['report_id'] ?? 0);
        $filter = $this->normalizeFilter($_POST['filter'] ?? 'new');

        if ($reportId <= 0) {
            $this->redirect('/reports?filter=' . urlencode($filter) . '&error=invalid_report');
        }

        $updated = Database::query(
            "UPDATE reports SET status = 'reviewed' WHERE id = ? AND status = 'pending'",
            [$reportId]
        );

        if ($updated->rowCount() < 1) {
            $this->redirect('/reports?filter=' . urlencode($filter) . '&error=report_not_found');
        }

        $this->redirect('/reports?filter=' . urlencode($filter) . '&success=report_handled');
    }

    public function submit() {
        Auth::requireAuth();
        Auth::csrfValidate();
        $userId = Auth::user()->id;
        $type = $_POST['target_type'] ?? ''; // user, chat, message
        $targetId = (int)($_POST['target_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if (!in_array($type, ['user', 'chat', 'message'], true) || $targetId <= 0 || $reason === '' || mb_strlen($reason) > 1000) {
            $this->json(['error' => 'Invalid report'], 400);
        }

        if ($type === 'user') {
            if ($targetId === (int)$userId) {
                $this->json(['error' => 'You cannot report yourself'], 400);
            }

            $targetUser = Database::query("SELECT id FROM users WHERE id = ?", [$targetId])->fetch();
            if (!$targetUser) {
                $this->json(['error' => 'Invalid report target'], 404);
            }
        }

        if ($type === 'message') {
            $targetMessage = Database::query("SELECT user_id FROM messages WHERE id = ?", [$targetId])->fetch();
            if (!$targetMessage) {
                $this->json(['error' => 'Invalid report target'], 404);
            }

            if ((int)$targetMessage->user_id === (int)$userId) {
                $this->json(['error' => 'You cannot report your own content'], 400);
            }
        }

        if ($type === 'chat') {
            $targetChat = Database::query("SELECT created_by FROM chats WHERE id = ?", [$targetId])->fetch();
            if (!$targetChat) {
                $this->json(['error' => 'Invalid report target'], 404);
            }

            if ((int)$targetChat->created_by === (int)$userId) {
                $this->json(['error' => 'You cannot report your own content'], 400);
            }
        }

        Database::query("INSERT INTO reports (reporter_id, target_type, target_id, reason) VALUES (?, ?, ?, ?)", 
            [$userId, $type, $targetId, $reason]);

        $admins = Database::query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
        foreach ($admins as $admin) {
            Notification::create($admin->id, 'report', 'New Report', 'A new report was submitted and is pending review.', '/reports');
        }

        $this->json(['success' => true]);
    }
}