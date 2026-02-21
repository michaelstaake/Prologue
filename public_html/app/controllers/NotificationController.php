<?php
class NotificationController extends Controller {
    private const MAX_SEEN_NOTIFICATIONS = 300;

    private function getSeenSettingKey($userId) {
        return 'notif_seen_' . (int)$userId;
    }

    private function getSidebarStateSettingKey($userId) {
        return 'notif_sidebar_expanded_' . (int)$userId;
    }

    private function getSeenNotificationIds($userId) {
        $raw = Setting::get($this->getSeenSettingKey($userId));
        if (!$raw) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $value) {
            $id = (int)$value;
            if ($id > 0) {
                $normalized[$id] = true;
            }
        }

        return array_values(array_map('intval', array_keys($normalized)));
    }

    private function saveSeenNotificationIds($userId, $ids) {
        $normalized = [];
        foreach ($ids as $value) {
            $id = (int)$value;
            if ($id > 0) {
                $normalized[$id] = true;
            }
        }

        $uniqueIds = array_values(array_map('intval', array_keys($normalized)));
        if (count($uniqueIds) > self::MAX_SEEN_NOTIFICATIONS) {
            $uniqueIds = array_slice($uniqueIds, -self::MAX_SEEN_NOTIFICATIONS);
        }

        Setting::set($this->getSeenSettingKey($userId), json_encode($uniqueIds));
        return $uniqueIds;
    }

    private function saveSidebarExpanded($userId, $expanded) {
        Setting::set($this->getSidebarStateSettingKey($userId), $expanded ? '1' : '0');
    }

    public function getAll() {
        Auth::requireAuth();
        $userId = Auth::user()->id;
        $notifs = Database::query("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20", [$userId])->fetchAll();
        $seenIds = $this->getSeenNotificationIds($userId);
        $incomingFriendRequestCount = (int)Database::query(
            "SELECT COUNT(*) FROM friends WHERE friend_id = ? AND status = 'pending'",
            [$userId]
        )->fetchColumn();

        $this->json([
            'notifications' => $notifs,
            'seen_ids' => $seenIds,
            'incoming_friend_request_count' => $incomingFriendRequestCount
        ]);
    }

    public function updateSidebarState() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $expanded = (int)($_POST['expanded'] ?? 0) === 1;
        $this->saveSidebarExpanded(Auth::user()->id, $expanded);

        $this->json(['success' => true, 'sidebar_expanded' => $expanded]);
    }

    public function markSeen() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $userId = Auth::user()->id;
        $rawIds = $_POST['ids'] ?? '';

        $incoming = [];
        if (is_array($rawIds)) {
            $incoming = $rawIds;
        } elseif (is_string($rawIds) && trim($rawIds) !== '') {
            $incoming = explode(',', $rawIds);
        }

        if (empty($incoming)) {
            $this->json(['success' => true, 'seen_ids' => $this->getSeenNotificationIds($userId)]);
        }

        $existingIds = $this->getSeenNotificationIds($userId);
        $mergedIds = array_merge($existingIds, $incoming);
        $savedIds = $this->saveSeenNotificationIds($userId, $mergedIds);

        $this->json(['success' => true, 'seen_ids' => $savedIds]);
    }

    public function markRead() {
        Auth::requireAuth();
        Auth::csrfValidate();
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->json(['error' => 'Invalid notification'], 400);
        }

        Database::query("UPDATE notifications SET `read` = 1 WHERE id = ? AND user_id = ?", [$id, Auth::user()->id]);
        $existingIds = $this->getSeenNotificationIds(Auth::user()->id);
        $this->saveSeenNotificationIds(Auth::user()->id, array_merge($existingIds, [$id]));
        $this->json(['success' => true]);
    }
}