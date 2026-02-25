<?php
class PostController extends Controller {
    public function create() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $userId = (int)Auth::user()->id;
        $content = Post::normalizeContent($_POST['content'] ?? '');

        if (!Post::isContentValid($content)) {
            $this->json(['error' => 'Posts must be between 1 and 500 characters'], 400);
        }

        $post = Post::create($userId, $content);
        if (!$post) {
            $this->json(['error' => 'Unable to create post'], 500);
        }

        $this->json(['success' => true, 'post_id' => (int)$post->id]);
    }

    public function react() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $userId = (int)Auth::user()->id;
        $postId = (int)($_POST['post_id'] ?? 0);
        $reactionCode = Post::normalizeReactionCode($_POST['reaction_code'] ?? '');

        if ($postId <= 0) {
            $this->json(['error' => 'Invalid payload'], 400);
        }
        if ($reactionCode === '') {
            $this->json(['error' => 'Invalid reaction'], 400);
        }

        $post = Post::findById($postId);
        if (!$post) {
            $this->json(['error' => 'Post not found'], 404);
        }

        $postOwnerId = (int)($post->user_id ?? 0);
        if (!Post::canUserReactToPost($userId, $postOwnerId)) {
            $this->json(['error' => 'Only friends can react to posts'], 403);
        }

        $existing = Database::query(
            "SELECT reaction_code FROM post_reactions WHERE post_id = ? AND user_id = ? LIMIT 1",
            [$postId, $userId]
        )->fetch();

        $action = 'set';
        if ($existing) {
            $existingCode = Post::normalizeReactionCode($existing->reaction_code ?? '');
            if ($existingCode === $reactionCode) {
                Database::query("DELETE FROM post_reactions WHERE post_id = ? AND user_id = ?", [$postId, $userId]);
                $action = 'removed';
            } else {
                Database::query(
                    "UPDATE post_reactions SET reaction_code = ? WHERE post_id = ? AND user_id = ?",
                    [$reactionCode, $postId, $userId]
                );
            }
        } else {
            Database::query(
                "INSERT INTO post_reactions (post_id, user_id, reaction_code) VALUES (?, ?, ?)",
                [$postId, $userId, $reactionCode]
            );
        }

        if ($action === 'set' && $postOwnerId > 0 && $postOwnerId !== $userId) {
            $actorUsername = User::normalizeUsername(Auth::user()->username ?? '') ?: 'Someone';
            $reactionDisplayByCode = [
                '1F44D' => '👍 Like',
                '1F44E' => '👎 Dislike',
                '2665' => '♥ Love',
                '1F923' => '🤣 Laugh',
                '1F622' => '😢 Cry',
                '1F436' => '🐶 Pup',
                '1F4A9' => '💩 Poop'
            ];
            $reactionDisplay = $reactionDisplayByCode[$reactionCode] ?? $reactionCode;
            Notification::create(
                $postOwnerId,
                'report',
                'Post Reaction',
                $actorUsername . ' reacted with ' . $reactionDisplay . ' to your post',
                '/posts'
            );
        }

        $this->json(['success' => true, 'action' => $action]);
    }
}
