<?php
class UserController extends Controller {
    public function profile($params) {
        Auth::requireAuth();
        $currentUserId = Auth::user()->id;
        $formattedNumber = (string)($params['user_number'] ?? '');
        if (!preg_match('/^\d{4}-\d{4}-\d{4}-\d{4}$/', $formattedNumber)) {
            $this->flash('error', 'user_not_found');
            $this->redirect('/');
        }

        $profile = User::findByUserNumber(str_replace('-', '', $formattedNumber));
        if (!$profile) {
            $this->flash('error', 'user_not_found');
            $this->redirect('/');
        }

        User::attachEffectiveStatus($profile);

                $friendCount = (int)Database::query(
                        "SELECT COUNT(*)
                         FROM friends
                         WHERE status = 'accepted'
                             AND (user_id = ? OR friend_id = ?)",
                        [$profile->id, $profile->id]
                )->fetchColumn();

        $friendshipStatus = null;
        $friendshipDirection = null;
        $personalChatNumber = null;
        $isFavorite = false;
        $canReactToPosts = false;
        if ((int)$profile->id !== (int)$currentUserId) {
            $friendship = Database::query(
                "SELECT user_id, friend_id, status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?) LIMIT 1",
                [$currentUserId, $profile->id, $profile->id, $currentUserId]
            )->fetch();
            $friendshipStatus = $friendship->status ?? null;
            $canReactToPosts = ($friendshipStatus ?? null) === 'accepted';
            if ($friendship) {
                $friendshipDirection = ((int)$friendship->user_id === (int)$currentUserId) ? 'outgoing' : 'incoming';
                if (($friendshipStatus ?? null) === 'accepted') {
                    $chat = Chat::getOrCreatePersonalChat($currentUserId, $currentUserId, $profile->id);
                    $personalChatNumber = User::formatUserNumber($chat->chat_number);
                    $isFavorite = (int)Database::query(
                        "SELECT COUNT(*) FROM friend_favorites WHERE user_id = ? AND favorite_user_id = ?",
                        [$currentUserId, $profile->id]
                    )->fetchColumn() > 0;
                }
            }
        }

        $posts = Post::getByUserId((int)$profile->id, (int)$currentUserId);

        $this->view('profile', [
            'profile' => $profile,
            'currentUserId' => $currentUserId,
            'friendshipStatus' => $friendshipStatus,
            'friendshipDirection' => $friendshipDirection,
            'personalChatNumber' => $personalChatNumber,
            'isFavorite' => $isFavorite,
            'friendCount' => $friendCount,
            'posts' => $posts,
            'canReactToPosts' => $canReactToPosts
        ]);
    }
}