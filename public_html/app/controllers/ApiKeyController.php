<?php

class ApiKeyController extends Controller {

    public function index() {
        Auth::requireAuth();
        $user = Auth::user();
        $userId = (int)$user->id;

        ApiKey::cleanupExpiredKeys($userId);

        $keys = ApiKey::getAllForUser($userId);

        $groupChats = Database::query(
            "SELECT c.id, c.chat_number, c.title
             FROM chats c
             JOIN chat_members cm ON cm.chat_id = c.id
             WHERE cm.user_id = ? AND c.type = 'group' AND c.created_by = ? AND c.deleted_at IS NULL
             ORDER BY c.title ASC, c.created_at DESC",
            [$userId, $userId]
        )->fetchAll();

        $this->view('apikeys', [
            'user' => $user,
            'csrf' => $this->csrfToken(),
            'keys' => $keys,
            'groupChats' => $groupChats,
        ]);
    }

    public function create() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $user = Auth::user();
        $userId = (int)$user->id;

        $name = trim($_POST['name'] ?? '');
        $type = strtolower(trim($_POST['type'] ?? ''));
        $expiry = trim($_POST['expiry'] ?? '30d');
        $allowedIps = trim($_POST['allowed_ips'] ?? '');
        $chatIds = $_POST['chat_ids'] ?? [];

        if ($name === '' || mb_strlen($name) > 100) {
            $this->json(['error' => 'Name is required (max 100 characters)'], 400);
            return;
        }
        if (!in_array($type, ['bot', 'user'], true)) {
            $this->json(['error' => 'Invalid key type'], 400);
            return;
        }
        if ($type === 'bot') {
            if (!is_array($chatIds)) {
                $chatIds = array_filter(array_map('trim', explode(',', (string)$chatIds)));
            }
            $chatIds = array_map('intval', $chatIds);
            $chatIds = array_filter($chatIds, fn($id) => $id > 0);
            if (empty($chatIds)) {
                $this->json(['error' => 'Bot keys require at least one chat'], 400);
                return;
            }
        }

        $cleanedIps = null;
        if ($allowedIps !== '') {
            $ipParts = array_map('trim', explode(',', $allowedIps));
            $ipParts = array_filter($ipParts, fn($ip) => $ip !== '');
            foreach ($ipParts as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    $this->json(['error' => 'Invalid IP address: ' . $ip], 400);
                    return;
                }
            }
            $cleanedIps = implode(',', $ipParts);
        }

        $expiresAt = null;
        switch ($expiry) {
            case '24h':   $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours')); break;
            case '7d':    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days')); break;
            case '30d':   $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days')); break;
            case '1y':    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 year')); break;
            case 'never': $expiresAt = null; break;
            default:      $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        }

        $allowedChats = null;
        if ($type === 'bot' && !empty($chatIds)) {
            foreach ($chatIds as $chatId) {
                $owned = Database::query(
                    "SELECT c.id FROM chats c
                     JOIN chat_members cm ON cm.chat_id = c.id AND cm.user_id = ?
                     WHERE c.id = ? AND c.type = 'group' AND c.created_by = ? AND c.deleted_at IS NULL",
                    [$userId, $chatId, $userId]
                )->fetch();
                if (!$owned) {
                    $this->json(['error' => 'You can only grant bot access to group chats you own'], 403);
                    return;
                }
            }
            $allowedChats = implode(',', $chatIds);
        }

        if (ApiKey::activeCountForUser($userId) >= 25) {
            $this->json(['error' => 'Maximum of 25 active API keys reached'], 400);
            return;
        }

        $rawKey = ApiKey::generateKey();
        $keyId = ApiKey::create($userId, $rawKey, $name, $type, $cleanedIps, $allowedChats, $expiresAt);

        $this->json([
            'success' => true,
            'api_key' => $rawKey,
            'key_id' => $keyId,
            'name' => $name,
            'type' => $type,
        ]);
    }

    public function expire() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $keyId = (int)($_POST['key_id'] ?? 0);
        $userId = (int)Auth::user()->id;

        if ($keyId <= 0) {
            $this->json(['error' => 'Invalid key'], 400);
            return;
        }

        ApiKey::expireKey($keyId, $userId);
        $this->json(['success' => true]);
    }

    public function update() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $userId = (int)Auth::user()->id;
        $keyId = (int)($_POST['key_id'] ?? 0);

        if ($keyId <= 0) {
            $this->json(['error' => 'Invalid key'], 400);
            return;
        }

        $key = ApiKey::getById($keyId, $userId);
        if (!$key) {
            $this->json(['error' => 'Key not found'], 404);
            return;
        }
        if ($key->status !== 'active') {
            $this->json(['error' => 'Cannot update an expired key'], 400);
            return;
        }

        // Validate and clean IPs
        $allowedIps = trim($_POST['allowed_ips'] ?? '');
        $cleanedIps = null;
        if ($allowedIps !== '') {
            $ipParts = array_map('trim', explode(',', $allowedIps));
            $ipParts = array_filter($ipParts, fn($ip) => $ip !== '');
            foreach ($ipParts as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    $this->json(['error' => 'Invalid IP address: ' . $ip], 400);
                    return;
                }
            }
            $cleanedIps = implode(',', $ipParts);
        }

        // Validate and clean chats (for bot keys)
        $allowedChats = null;
        if ($key->type === 'bot') {
            $chatIds = $_POST['chat_ids'] ?? [];
            if (!is_array($chatIds)) {
                $chatIds = array_filter(array_map('trim', explode(',', (string)$chatIds)));
            }
            $chatIds = array_map('intval', $chatIds);
            $chatIds = array_filter($chatIds, fn($id) => $id > 0);

            if (empty($chatIds)) {
                $this->json(['error' => 'Bot keys require at least one chat'], 400);
                return;
            }

            foreach ($chatIds as $chatId) {
                $owned = Database::query(
                    "SELECT c.id FROM chats c
                     JOIN chat_members cm ON cm.chat_id = c.id AND cm.user_id = ?
                     WHERE c.id = ? AND c.type = 'group' AND c.created_by = ? AND c.deleted_at IS NULL",
                    [$userId, $chatId, $userId]
                )->fetch();
                if (!$owned) {
                    $this->json(['error' => 'You can only grant bot access to group chats you own'], 403);
                    return;
                }
            }
            $allowedChats = implode(',', $chatIds);
        }

        ApiKey::updateIps($keyId, $userId, $cleanedIps);
        if ($key->type === 'bot') {
            ApiKey::updateChats($keyId, $userId, $allowedChats);
        }

        $this->json(['success' => true]);
    }

    public function viewKey() {
        Auth::requireAuth();
        Auth::csrfValidate();

        $userId = (int)Auth::user()->id;
        $keyId = (int)($_POST['key_id'] ?? 0);

        if ($keyId <= 0) {
            $this->json(['error' => 'Invalid key'], 400);
            return;
        }

        $key = ApiKey::getById($keyId, $userId);
        if (!$key) {
            $this->json(['error' => 'Key not found'], 404);
            return;
        }

        if ($key->type !== 'bot') {
            $this->json(['error' => 'User keys cannot be viewed after creation for security reasons'], 403);
            return;
        }

        $this->json([
            'success' => true,
            'api_key' => $key->api_key,
        ]);
    }

    public function docs() {
        Auth::requireAuth();
        $this->view('apikeys_docs', [
            'user' => Auth::user(),
            'csrf' => $this->csrfToken(),
        ]);
    }

    public function botSendMessage() {
        $apiKeyRaw = trim($_POST['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '');
        $chatNumber = trim($_POST['chat_number'] ?? '');
        $text = trim($_POST['text'] ?? '');
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($apiKeyRaw === '' || !preg_match('/^[a-f0-9]{64}$/', $apiKeyRaw)) {
            $this->json(['error' => 'Invalid or missing API key'], 401);
            return;
        }
        if ($chatNumber === '') {
            $this->json(['error' => 'chat_number is required'], 400);
            return;
        }
        if ($text === '') {
            $this->json(['error' => 'text is required'], 400);
            return;
        }
        if (mb_strlen($text) > 16384) {
            $this->json(['error' => 'Text exceeds 16,384 character limit'], 400);
            return;
        }

        $keyRecord = ApiKey::findByKey($apiKeyRaw);
        if (!$keyRecord) {
            $this->json(['error' => 'Invalid API key'], 401);
            return;
        }

        if ((int)($keyRecord->is_banned ?? 0) === 1) {
            $this->json(['error' => 'Account is banned'], 403);
            return;
        }

        if ($keyRecord->status !== 'active') {
            $this->json(['error' => 'API key is expired'], 403);
            return;
        }

        if ($keyRecord->expires_at !== null && strtotime($keyRecord->expires_at) <= time()) {
            Database::query("UPDATE api_keys SET status = 'expired' WHERE id = ?", [(int)$keyRecord->id]);
            $this->json(['error' => 'API key has expired'], 403);
            return;
        }

        if (!ApiKey::isIpAllowed($keyRecord->allowed_ips, $clientIp)) {
            $this->json(['error' => 'IP address not allowed'], 403);
            return;
        }

        if ($keyRecord->type !== 'bot') {
            $this->json(['error' => 'This endpoint is for Bot API keys only'], 403);
            return;
        }

        $cleanNumber = preg_replace('/[^0-9]/', '', $chatNumber);
        $chat = Database::query(
            "SELECT id, chat_number, type, deleted_at FROM chats WHERE chat_number = ? LIMIT 1",
            [$cleanNumber]
        )->fetch();

        if (!$chat || $chat->deleted_at !== null) {
            $this->json(['error' => 'Chat not found'], 404);
            return;
        }

        if ($chat->type !== 'group') {
            $this->json(['error' => 'Bot keys can only send messages to group chats'], 403);
            return;
        }

        $chatId = (int)$chat->id;

        if (!ApiKey::isChatAllowed($keyRecord->allowed_chats, $chatId)) {
            $this->json(['error' => 'This API key is not authorized for this chat'], 403);
            return;
        }

        $ownsChat = Database::query(
            "SELECT id FROM chats WHERE id = ? AND created_by = ?",
            [$chatId, (int)$keyRecord->user_id]
        )->fetch();
        if (!$ownsChat) {
            $this->json(['error' => 'API key owner no longer owns this chat'], 403);
            return;
        }

        $member = Database::query(
            "SELECT id FROM chat_members WHERE chat_id = ? AND user_id = ?",
            [$chatId, (int)$keyRecord->user_id]
        )->fetch();
        if (!$member) {
            $this->json(['error' => 'API key owner is no longer a member of this chat'], 403);
            return;
        }

        $content = Message::encodeMentionsForChat($chatId, $text);
        $botName = $keyRecord->name;
        Database::query(
            "INSERT INTO messages (chat_id, user_id, content, bot_name) VALUES (?, ?, ?, ?)",
            [$chatId, (int)$keyRecord->user_id, $content, $botName]
        );
        $messageId = (int)Database::getInstance()->lastInsertId();

        ApiKey::logUsage((int)$keyRecord->id, $clientIp, 'bot/send');

        $members = Database::query(
            "SELECT user_id FROM chat_members WHERE chat_id = ? AND user_id != ?",
            [$chatId, (int)$keyRecord->user_id]
        )->fetchAll();
        foreach ($members as $m) {
            Notification::create(
                $m->user_id,
                'message',
                'New Message',
                mb_substr($text, 0, 50),
                '/c/' . User::formatUserNumber($chat->chat_number)
            );
        }

        $this->json(['success' => true, 'message_id' => $messageId]);
    }
}
