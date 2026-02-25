<?php
class Post extends Model {
    public const MAX_CONTENT_LENGTH = 500;
    public const REACTION_CODES = [
        '1F44D',
        '1F44E',
        '2665',
        '1F923',
        '1F622',
        '1F436',
        '1F4A9'
    ];

    public static function normalizeReactionCode($value): string {
        $code = strtoupper(trim((string)$value));
        $code = preg_replace('/[^0-9A-F]/', '', $code) ?? '';
        if ($code === '') {
            return '';
        }

        return in_array($code, self::REACTION_CODES, true) ? $code : '';
    }

    public static function normalizeContent($value): string {
        return trim((string)$value);
    }

    public static function isContentValid($content): bool {
        $normalized = self::normalizeContent($content);
        if ($normalized === '') {
            return false;
        }

        return mb_strlen($normalized, 'UTF-8') <= self::MAX_CONTENT_LENGTH;
    }

    public static function create(int $userId, string $content): ?object {
        $normalized = self::normalizeContent($content);
        if (!self::isContentValid($normalized)) {
            return null;
        }

        Database::query(
            "INSERT INTO posts (user_id, content) VALUES (?, ?)",
            [$userId, $normalized]
        );

        $postId = (int)Database::getInstance()->lastInsertId();
        if ($postId <= 0) {
            return null;
        }

        return Database::query(
            "SELECT id, user_id, content, created_at FROM posts WHERE id = ? LIMIT 1",
            [$postId]
        )->fetch() ?: null;
    }

    public static function findById(int $postId): ?object {
        if ($postId <= 0) {
            return null;
        }

        return Database::query(
            "SELECT id, user_id, content, created_at FROM posts WHERE id = ? LIMIT 1",
            [$postId]
        )->fetch() ?: null;
    }

    public static function getByUserId(int $profileUserId, int $currentUserId, int $limit = 30): array {
        if ($profileUserId <= 0) {
            return [];
        }

        $safeLimit = max(1, min(100, $limit));
        $rows = Database::query(
            "SELECT p.id, p.user_id, p.content, p.created_at
             FROM posts p
             WHERE p.user_id = ?
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT $safeLimit",
            [$profileUserId]
        )->fetchAll();

        $posts = is_array($rows) ? $rows : [];
        self::attachReactions($posts, $currentUserId);

        return $posts;
    }

    public static function attachReactions(array &$posts, int $currentUserId): void {
        if (count($posts) === 0) {
            return;
        }

        $postIds = [];
        foreach ($posts as $post) {
            $postId = (int)($post->id ?? 0);
            if ($postId <= 0) {
                continue;
            }
            $postIds[$postId] = true;
            $post->reactions = [];
        }

        if (count($postIds) === 0) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $rows = Database::query(
            "SELECT pr.post_id, pr.reaction_code, pr.user_id, u.username
             FROM post_reactions pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.post_id IN ($placeholders)
             ORDER BY pr.id ASC",
            array_keys($postIds)
        )->fetchAll();

        $aggregatedByPost = [];
        foreach ($rows as $row) {
            $postId = (int)($row->post_id ?? 0);
            $reactionCode = self::normalizeReactionCode($row->reaction_code ?? '');
            $reactionUserId = (int)($row->user_id ?? 0);
            $username = User::normalizeUsername($row->username ?? '');

            if ($postId <= 0 || $reactionCode === '' || $reactionUserId <= 0 || $username === '') {
                continue;
            }

            if (!isset($aggregatedByPost[$postId])) {
                $aggregatedByPost[$postId] = [];
            }
            if (!isset($aggregatedByPost[$postId][$reactionCode])) {
                $aggregatedByPost[$postId][$reactionCode] = [
                    'reaction_code' => $reactionCode,
                    'count' => 0,
                    'users' => [],
                    'reacted_by_current_user' => false
                ];
            }

            $aggregatedByPost[$postId][$reactionCode]['count'] += 1;
            $aggregatedByPost[$postId][$reactionCode]['users'][] = $username;
            if ($reactionUserId === $currentUserId) {
                $aggregatedByPost[$postId][$reactionCode]['reacted_by_current_user'] = true;
            }
        }

        foreach ($posts as $post) {
            $postId = (int)($post->id ?? 0);
            if (!isset($aggregatedByPost[$postId])) {
                $post->reactions = [];
                continue;
            }

            $reactionRows = [];
            foreach (self::REACTION_CODES as $code) {
                if (!isset($aggregatedByPost[$postId][$code])) {
                    continue;
                }
                $entry = $aggregatedByPost[$postId][$code];
                $reactionRows[] = (object)[
                    'reaction_code' => (string)$entry['reaction_code'],
                    'count' => (int)$entry['count'],
                    'users' => array_values($entry['users']),
                    'reacted_by_current_user' => (bool)$entry['reacted_by_current_user']
                ];
            }

            $post->reactions = $reactionRows;
        }
    }

    public static function areUsersFriends(int $firstUserId, int $secondUserId): bool {
        if ($firstUserId <= 0 || $secondUserId <= 0 || $firstUserId === $secondUserId) {
            return false;
        }

        $friendship = Database::query(
            "SELECT id
             FROM friends
             WHERE status = 'accepted'
               AND ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
             LIMIT 1",
            [$firstUserId, $secondUserId, $secondUserId, $firstUserId]
        )->fetch();

        return (bool)$friendship;
    }

    public static function canUserReactToPost(int $viewerUserId, int $postOwnerUserId): bool {
        return self::areUsersFriends($viewerUserId, $postOwnerUserId);
    }
}
