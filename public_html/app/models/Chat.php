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
}
