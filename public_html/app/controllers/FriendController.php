<?php
class FriendController extends Controller {
    public function index() {
        Auth::requireAuth();
        $userId = Auth::user()->id;
        $selectedTab = strtolower(trim((string)($_GET['tab'] ?? 'all')));
        if (!in_array($selectedTab, ['favorites', 'online', 'all', 'requests'], true)) {
            $selectedTab = 'all';
        }

        $selectedRequestsTab = strtolower(trim((string)($_GET['requests'] ?? 'incoming')));
        if (!in_array($selectedRequestsTab, ['incoming', 'outgoing'], true)) {
            $selectedRequestsTab = 'incoming';
        }

        $friends = Database::query(
            "SELECT u.*, f.status,
                    CASE WHEN ff.id IS NULL THEN 0 ELSE 1 END AS is_favorite
             FROM friends f
             JOIN users u ON (f.friend_id = u.id OR f.user_id = u.id)
             LEFT JOIN friend_favorites ff ON ff.user_id = ? AND ff.favorite_user_id = u.id
             WHERE (f.user_id = ? OR f.friend_id = ?) AND u.id != ? AND f.status = 'accepted'
             ORDER BY u.username ASC",
            [$userId, $userId, $userId, $userId]
        )->fetchAll();
        $pendingIncoming = Database::query(
            "SELECT f.user_id AS requester_id,
                    u.id,
                    u.username,
                    u.user_number,
                    u.avatar_filename,
                    u.presence_status,
                    u.last_active_at,
                    f.created_at
             FROM friends f
             JOIN users u ON f.user_id = u.id
             WHERE f.friend_id = ? AND f.status = 'pending'
             ORDER BY f.created_at DESC",
            [$userId]
        )->fetchAll();
        $pendingOutgoing = Database::query(
            "SELECT f.friend_id AS target_user_id,
                u.id,
                    u.username,
                    u.user_number,
                    u.avatar_filename,
                    u.presence_status,
                    u.last_active_at,
                    f.created_at
             FROM friends f
             JOIN users u ON f.friend_id = u.id
             WHERE f.user_id = ? AND f.status = 'pending'
             ORDER BY f.created_at DESC",
            [$userId]
        )->fetchAll();
        $incomingRequestCount = count($pendingIncoming);

        User::attachEffectiveStatusList($friends);
        User::attachEffectiveStatusList($pendingIncoming);
        User::attachEffectiveStatusList($pendingOutgoing);

        $visibleFriends = $friends;
        if ($selectedTab === 'favorites') {
            $visibleFriends = array_values(array_filter($friends, static function($friend) {
                return (int)($friend->is_favorite ?? 0) === 1;
            }));
        } elseif ($selectedTab === 'online') {
            $visibleFriends = array_values(array_filter($friends, static function($friend) {
                return ($friend->effective_status ?? 'offline') !== 'offline';
            }));
        }

        $this->view('friends', [
            'friends' => $friends,
            'visibleFriends' => $visibleFriends,
            'pendingIncoming' => $pendingIncoming,
            'pendingOutgoing' => $pendingOutgoing,
            'incomingRequestCount' => $incomingRequestCount,
            'selectedTab' => $selectedTab,
            'selectedRequestsTab' => $selectedRequestsTab,
            'csrf' => $this->csrfToken()
        ]);
    }

    public function sendRequest() {
        Auth::requireAuth();
        Auth::csrfValidate();
        $userId = Auth::user()->id;
        $targetNumber = str_replace('-', '', $_POST['user_number']);
        $target = User::findByUserNumber($targetNumber);
        if (!$target) {
            $this->json(['error' => 'Invalid user'], 400);
        }

        if ((int)$target->id === (int)$userId) {
            $this->json(['error' => 'You cannot send a friend request to yourself'], 400);
        }

        $exists = Database::query("SELECT id FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)", [$userId, $target->id, $target->id, $userId])->fetch();
        if ($exists) {
            $this->json(['error' => 'Friend relationship already exists'], 409);
        }

        Database::query("INSERT IGNORE INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')", [$userId, $target->id]);
        Notification::create($target->id, 'friend_request', 'Friend Request', Auth::user()->username . ' sent you a friend request', '/?tab=requests&requests=incoming');
        $this->json(['success' => true]);
    }

    public function acceptRequest() {
        Auth::requireAuth();
        Auth::csrfValidate();
        $userId = Auth::user()->id;
        $requesterId = (int)($_POST['requester_id'] ?? 0);

        if ($requesterId <= 0) {
            $this->json(['error' => 'Invalid requester'], 400);
        }

        Database::query("UPDATE friends SET status = 'accepted' WHERE friend_id = ? AND user_id = ?", [$userId, $requesterId]);

        $row = Database::query("SELECT id FROM friends WHERE friend_id = ? AND user_id = ? AND status = 'accepted'", [$userId, $requesterId])->fetch();
        if (!$row) {
            $this->json(['error' => 'Request not found'], 404);
        }

        $chat = Chat::getOrCreatePersonalChat($userId, $userId, $requesterId);
        Notification::create($requesterId, 'friend_request_accepted', 'Friend Request Accepted', Auth::user()->username . ' accepted your request', '/u/' . User::formatUserNumber(Auth::user()->user_number));

        $this->json(['success' => true, 'chat_number' => $chat->chat_number]);
    }

    public function cancelRequest() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $userId = (int)Auth::user()->id;
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);

        if ($targetUserId <= 0 || $targetUserId === $userId) {
            $this->json(['error' => 'Invalid request target'], 400);
        }

        $result = Database::query(
            "DELETE FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'",
            [$userId, $targetUserId]
        );

        if ($result->rowCount() <= 0) {
            $this->json(['error' => 'Request not found or already accepted'], 404);
        }

        $this->json(['success' => true]);
    }

    public function unfriend() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $userId = (int)Auth::user()->id;
        $targetUserId = (int)($_POST['user_id'] ?? 0);

        if ($targetUserId <= 0 || $targetUserId === $userId) {
            $this->json(['error' => 'Invalid user'], 400);
        }

        $result = Database::query(
            "DELETE FROM friends
             WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
               AND status = 'accepted'",
            [$userId, $targetUserId, $targetUserId, $userId]
        );

        if ($result->rowCount() <= 0) {
            $this->json(['error' => 'Friendship not found'], 404);
        }

        Database::query(
            "DELETE FROM friend_favorites
             WHERE (user_id = ? AND favorite_user_id = ?)
                OR (user_id = ? AND favorite_user_id = ?)",
            [$userId, $targetUserId, $targetUserId, $userId]
        );

        $this->json(['success' => true]);
    }

    public function toggleFavorite() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $userId = (int)Auth::user()->id;
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $makeFavorite = (int)($_POST['favorite'] ?? 0) === 1;

        if ($targetUserId <= 0 || $targetUserId === $userId) {
            $this->json(['error' => 'Invalid user'], 400);
        }

        $friendship = Database::query(
            "SELECT id FROM friends
             WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
               AND status = 'accepted'
             LIMIT 1",
            [$userId, $targetUserId, $targetUserId, $userId]
        )->fetch();

        if (!$friendship) {
            $this->json(['error' => 'Only accepted friends can be favorited'], 403);
        }

        if ($makeFavorite) {
            Database::query(
                "INSERT IGNORE INTO friend_favorites (user_id, favorite_user_id) VALUES (?, ?)",
                [$userId, $targetUserId]
            );
        } else {
            Database::query(
                "DELETE FROM friend_favorites WHERE user_id = ? AND favorite_user_id = ?",
                [$userId, $targetUserId]
            );
        }

        $isFavorite = (int)Database::query(
            "SELECT COUNT(*) FROM friend_favorites WHERE user_id = ? AND favorite_user_id = ?",
            [$userId, $targetUserId]
        )->fetchColumn() > 0;

        $this->json(['success' => true, 'is_favorite' => $isFavorite]);
    }
}