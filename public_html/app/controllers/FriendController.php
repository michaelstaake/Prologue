<?php
class FriendController extends Controller {
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