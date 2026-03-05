<?php
class Chat extends Model {
	public static function supportsSoftDelete(): bool {
		static $supports = null;
		if ($supports !== null) {
			return $supports;
		}

		$result = self::query(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chats' AND COLUMN_NAME = 'deleted_at'"
		)->fetchColumn();

		$supports = ((int)$result) > 0;
		return $supports;
	}

	public static function supportsDeletedBy(): bool {
		static $supports = null;
		if ($supports !== null) {
			return $supports;
		}

		$result = self::query(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chats' AND COLUMN_NAME = 'deleted_by'"
		)->fetchColumn();

		$supports = ((int)$result) > 0;
		return $supports;
	}

	public static function normalizeType($type) {
		$normalized = strtolower(trim((string)$type));
		if ($normalized === 'dm') {
			return 'personal';
		}

		return $normalized === 'group' ? 'group' : 'personal';
	}

	public static function isGroupType($type) {
		return self::normalizeType($type) === 'group';
	}

	public static function generateUniqueChatNumber() {
		do {
			$chatNumber = str_pad((string) random_int(0, 9999999999999999), 16, '0', STR_PAD_LEFT);
			$existingChat = self::query("SELECT id FROM chats WHERE chat_number = ?", [$chatNumber])->fetch();
		} while ($existingChat);

		return $chatNumber;
	}

	public static function findPersonalChatBetween($userAId, $userBId) {
		return self::query(
			"SELECT c.*
			 FROM chats c
			 JOIN chat_members cma ON cma.chat_id = c.id AND cma.user_id = ?
			 JOIN chat_members cmb ON cmb.chat_id = c.id AND cmb.user_id = ?
			 WHERE c.type IN ('personal', 'dm')
			   AND (SELECT COUNT(*) FROM chat_members cm WHERE cm.chat_id = c.id) = 2
			 ORDER BY c.id ASC
			 LIMIT 1",
			[(int)$userAId, (int)$userBId]
		)->fetch();
	}

	public static function createPersonalChat($createdById, $userAId, $userBId) {
		$chatNumber = self::generateUniqueChatNumber();

		self::query("INSERT INTO chats (chat_number, type, created_by) VALUES (?, 'personal', ?)", [$chatNumber, (int)$createdById]);
		$chatId = (int)self::db()->lastInsertId();
		self::query("INSERT INTO chat_members (chat_id, user_id) VALUES (?, ?), (?, ?)", [$chatId, (int)$userAId, $chatId, (int)$userBId]);

		return self::query("SELECT * FROM chats WHERE id = ?", [$chatId])->fetch();
	}

	public static function getOrCreatePersonalChat($createdById, $userAId, $userBId) {
		$existing = self::findPersonalChatBetween($userAId, $userBId);
		if ($existing) {
			return $existing;
		}

		return self::createPersonalChat($createdById, $userAId, $userBId);
	}

	public static function isSoftDeleted($chat): bool {
		if (!$chat || !self::supportsSoftDelete()) {
			return false;
		}

		return !empty($chat->deleted_at);
	}

	public static function supportsPolls(): bool {
		static $supports = null;
		if ($supports !== null) {
			return $supports;
		}

		$pollsTable = (int)self::query(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'polls'"
		)->fetchColumn();
		$optionsTable = (int)self::query(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'poll_options'"
		)->fetchColumn();
		$votesTable = (int)self::query(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'poll_votes'"
		)->fetchColumn();

		$supports = $pollsTable > 0 && $optionsTable > 0 && $votesTable > 0;
		return $supports;
	}

	public static function cleanupExpiredPollsForChat(int $chatId): void {
		if (!self::supportsPolls() || $chatId <= 0) {
			return;
		}

		self::query(
			"UPDATE polls
			 SET status = 'expired',
			     expired_at = COALESCE(expired_at, NOW())
			 WHERE chat_id = ?
			   AND status = 'active'
			   AND expires_at <= NOW()",
			[$chatId]
		);
	}

	public static function getActivePollForChat(int $chatId, int $currentUserId = 0) {
		if (!self::supportsPolls() || $chatId <= 0) {
			return null;
		}

		self::cleanupExpiredPollsForChat($chatId);

		$poll = self::query(
			"SELECT p.id, p.chat_id, p.creator_user_id, p.question, p.status, p.expires_at, p.expired_at, p.created_at,
			        u.username AS creator_username, u.email AS creator_email
			 FROM polls p
			 JOIN users u ON u.id = p.creator_user_id
			 WHERE p.chat_id = ?
			   AND p.status = 'active'
			   AND p.expires_at > NOW()
			 ORDER BY p.created_at DESC
			 LIMIT 1",
			[$chatId]
		)->fetch();

		if (!$poll) {
			return null;
		}

		$poll->creator_username = User::decorateDeletedRetainedUsername($poll->creator_username ?? '', $poll->creator_email ?? null);
		unset($poll->creator_email);

		$options = self::query(
			"SELECT po.id, po.poll_id, po.option_text, po.sort_order,
			        COUNT(pv.id) AS vote_count,
			        GROUP_CONCAT(DISTINCT voter.username ORDER BY voter.username SEPARATOR ', ') AS voter_usernames
			 FROM poll_options po
			 LEFT JOIN poll_votes pv ON pv.poll_option_id = po.id AND pv.poll_id = po.poll_id
			 LEFT JOIN users voter ON voter.id = pv.user_id
			 WHERE po.poll_id = ?
			 GROUP BY po.id, po.poll_id, po.option_text, po.sort_order
			 ORDER BY po.sort_order ASC",
			[(int)$poll->id]
		)->fetchAll();

		$userVotedOptionId = 0;
		if ($currentUserId > 0) {
			$userVotedOptionId = (int)self::query(
				"SELECT poll_option_id
				 FROM poll_votes
				 WHERE poll_id = ? AND user_id = ?
				 LIMIT 1",
				[(int)$poll->id, $currentUserId]
			)->fetchColumn();
		}

		$totalVotes = (int)self::query(
			"SELECT COUNT(*) FROM poll_votes WHERE poll_id = ?",
			[(int)$poll->id]
		)->fetchColumn();

		foreach ($options as $option) {
			$option->vote_count = (int)($option->vote_count ?? 0);
			$option->voter_usernames = trim((string)($option->voter_usernames ?? ''));
			$option->voters = $option->voter_usernames === ''
				? []
				: array_values(array_filter(array_map('trim', explode(',', $option->voter_usernames)), static fn($value) => $value !== ''));
			$option->selected_by_current_user = $userVotedOptionId > 0 && (int)$option->id === $userVotedOptionId;
		}

		$poll->options = $options;
		$poll->total_votes = $totalVotes;
		$poll->user_has_voted = $userVotedOptionId > 0;
		$poll->user_voted_option_id = $userVotedOptionId;

		return $poll;
	}
}
