<?php
class Attachment extends Model {
    // Allowed MIME types per file extension (finfo detection)
    private const EXT_ALLOWED_MIMES = [
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'webp' => ['image/webp'],
        'mp4'  => ['video/mp4', 'video/quicktime'],
        'webm' => ['video/webm'],
        'pdf'  => ['application/pdf'],
        'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'doc'  => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        '7z'   => ['application/x-7z-compressed'],
    ];

    // Display category per extension
    private const EXT_CATEGORY = [
        'png' => 'image', 'jpg' => 'image', 'webp' => 'image',
    ];

    public static function extensionCategory(string $ext): string {
        return self::EXT_CATEGORY[$ext] ?? 'file';
    }

    public static function acceptedExtensions(): array {
        $raw = strtolower((string)(Setting::get('attachments_accepted_file_types') ?? 'png,jpg'));
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $knownExtensions = array_keys(self::EXT_ALLOWED_MIMES);
        $allowed = [];

        foreach ($parts as $part) {
            if ($part === 'jpeg') $part = 'jpg';
            if (in_array($part, $knownExtensions, true)) {
                $allowed[$part] = true;
            }
        }

        // Only fall back to defaults when the setting has never been saved (null = no DB row).
        // An empty string means the admin explicitly disabled all types.
        if (count($allowed) === 0 && Setting::get('attachments_accepted_file_types') === null) {
            $allowed = ['png' => true, 'jpg' => true];
        }

        return array_keys($allowed);
    }

    public static function acceptedMimeTypes(): array {
        $mimes = [];
        foreach (self::acceptedExtensions() as $ext) {
            foreach ((self::EXT_ALLOWED_MIMES[$ext] ?? []) as $mime) {
                $mimes[$mime] = true;
            }
        }
        return array_keys($mimes);
    }

    private static function parsePhpBytes(string $val): int {
        $val = trim($val);
        $unit = strtolower(substr($val, -1));
        $num = (int)$val;
        switch ($unit) {
            case 'g': return $num * 1024 * 1024 * 1024;
            case 'm': return $num * 1024 * 1024;
            case 'k': return $num * 1024;
            default:  return $num;
        }
    }

    public static function maxFileSizeBytes(): int {
        $hardCap = 512 * 1024 * 1024;

        $uploadMax = self::parsePhpBytes((string)ini_get('upload_max_filesize'));
        $postMax = self::parsePhpBytes((string)ini_get('post_max_size'));

        $phpLimit = $hardCap;
        if ($uploadMax > 0) {
            $phpLimit = min($phpLimit, $uploadMax);
        }
        if ($postMax > 0) {
            $phpLimit = min($phpLimit, $postMax);
        }

        $raw = (string)(Setting::get('attachments_maximum_file_size_mb') ?? '');
        $sizeMb = (int)preg_replace('/\D+/', '', $raw);
        if ($sizeMb > 0) {
            return min($sizeMb * 1024 * 1024, $phpLimit);
        }

        return $phpLimit;
    }

    public static function listPendingForChatUser(int $chatId, int $userId): array {
        try {
            $rows = Database::query(
                "SELECT a.id, a.original_name, a.file_name, a.file_extension, a.mime_type, a.file_size, a.width, a.height, a.created_at, u.user_number
                 FROM attachments a
                 JOIN users u ON u.id = a.user_id
                 WHERE chat_id = ? AND user_id = ? AND status = 'pending'
                 ORDER BY created_at ASC",
                [$chatId, $userId]
            )->fetchAll();
        } catch (Throwable $e) {
            return [];
        }

        foreach ($rows as $row) {
            $row->url = self::publicUrl((string)$row->user_number, (string)$row->file_name, (string)$row->file_extension);
        }

        return $rows;
    }

    public static function createPendingFromUpload(int $chatId, $user, array $file): array {
        $attachmentLogging = Setting::get('attachment_logging') === '1';
        $logFailure = function(string $reason, string $step) use ($attachmentLogging, $chatId, $user, $file): void {
            if (!$attachmentLogging) return;
            ErrorHandler::logToDirectory('attachment.log', 'upload_failed', [
                'step' => $step,
                'reason' => $reason,
                'chat_id' => $chatId,
                'user_id' => (int)($user->id ?? 0),
                'original_name' => $file['name'] ?? '',
                'size' => $file['size'] ?? 0,
            ]);
        };

        $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            $logFailure('attachment_upload_failed', 'php_upload_error:' . $errorCode);
            return ['error' => 'attachment_upload_failed'];
        }

        $tmpPath = $file['tmp_name'] ?? '';
        if (!is_string($tmpPath) || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
            $logFailure('attachment_upload_failed', 'invalid_tmp_file');
            return ['error' => 'attachment_upload_failed'];
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > self::maxFileSizeBytes()) {
            $logFailure('attachment_too_large', 'size_check');
            return ['error' => 'attachment_too_large'];
        }

        $originalName = self::normalizeOriginalName((string)($file['name'] ?? ''));
        if ($originalName === null || substr_count($originalName, '.') !== 1) {
            $logFailure('attachment_invalid_name', 'normalize_name');
            return ['error' => 'attachment_invalid_name'];
        }

        // Determine extension from filename and normalize
        $nameExt = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        if ($nameExt === 'jpeg') $nameExt = 'jpg';

        // Check extension is in the accepted list (from admin settings)
        if (!in_array($nameExt, self::acceptedExtensions(), true)) {
            $logFailure('attachment_invalid_type', 'extension_not_accepted:' . $nameExt);
            return ['error' => 'attachment_invalid_type'];
        }

        // Detect MIME type via finfo (reads file magic bytes, not extension)
        $fi = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $mime = $fi ? strtolower((string)finfo_file($fi, $tmpPath)) : '';

        if ($mime === '') {
            $logFailure('attachment_upload_failed', 'finfo_unavailable');
            return ['error' => 'attachment_upload_failed'];
        }

        // Check detected MIME is allowlisted for this extension
        $allowedMimes = self::EXT_ALLOWED_MIMES[$nameExt] ?? [];
        if (!in_array($mime, $allowedMimes, true)) {
            $logFailure('attachment_invalid_type', 'mime_not_allowed_for_ext:' . $mime . '_ext:' . $nameExt);
            return ['error' => 'attachment_invalid_type'];
        }

        $userId = (int)$user->id;
        $userNumber = preg_replace('/\D+/', '', (string)$user->user_number);
        if (!preg_match('/^\d{16}$/', $userNumber)) {
            $logFailure('attachment_upload_failed', 'invalid_user_number');
            return ['error' => 'attachment_upload_failed'];
        }

        // Compute SHA-256 hash of the uploaded file for deduplication
        $fileHashResult = hash_file('sha256', $tmpPath);
        $fileHash = ($fileHashResult !== false) ? $fileHashResult : null;

        // Check whether an identical file already exists on the server
        if ($fileHash !== null) {
            $dedupSource = Database::query(
                "SELECT a.id, a.file_size, a.width, a.height, a.file_name, a.file_extension, u.user_number
                 FROM attachments a
                 JOIN users u ON u.id = a.user_id
                 WHERE a.file_hash = ? AND a.file_extension = ? AND a.dedup_source_id IS NULL
                 LIMIT 1",
                [$fileHash, $nameExt]
            )->fetch();

            if ($dedupSource) {
                $fileBase = self::generateUniqueFileBaseForUser($userId);
                if ($fileBase === null) {
                    $logFailure('attachment_upload_failed', 'unique_filename_error');
                    return ['error' => 'attachment_upload_failed'];
                }

                $dedupStoredSize = (int)$dedupSource->file_size;
                $dedupWidth  = $dedupSource->width  !== null ? (int)$dedupSource->width  : null;
                $dedupHeight = $dedupSource->height !== null ? (int)$dedupSource->height : null;

                Database::query(
                    "INSERT INTO attachments (chat_id, user_id, original_name, file_name, file_extension, mime_type, file_size, width, height, file_hash, dedup_source_id, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
                    [$chatId, $userId, $originalName, $fileBase, $nameExt, $mime, $dedupStoredSize, $dedupWidth, $dedupHeight, $fileHash, (int)$dedupSource->id]
                );

                $id = (int)Database::getInstance()->lastInsertId();

                if ($attachmentLogging) {
                    $realUserNumber = preg_replace('/\D+/', '', (string)$dedupSource->user_number);
                    ErrorHandler::logToDirectory('attachment.log', 'upload_success', [
                        'chat_id' => $chatId,
                        'user_id' => $userId,
                        'original_name' => $originalName,
                        'file_name' => $fileBase . '.' . $nameExt,
                        'size' => $dedupStoredSize,
                        'width' => $dedupWidth,
                        'height' => $dedupHeight,
                        'dedup' => true,
                        'real_file' => $realUserNumber . '/' . $dedupSource->file_name . '.' . $dedupSource->file_extension,
                    ]);
                }

                return [
                    'success' => true,
                    'attachment' => [
                        'id' => $id,
                        'original_name' => $originalName,
                        'file_name' => $fileBase,
                        'file_extension' => $nameExt,
                        'mime_type' => $mime,
                        'file_size' => $dedupStoredSize,
                        'width' => $dedupWidth,
                        'height' => $dedupHeight,
                        'url' => self::publicUrl($userNumber, $fileBase, $nameExt)
                    ]
                ];
            }
        }

        $dir = self::directoryForUserNumber($userNumber);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $logFailure('attachment_upload_failed', 'storage_dir_error:' . $dir);
            return ['error' => 'attachment_upload_failed'];
        }

        $fileBase = self::generateUniqueFileBaseForUser($userId);
        if ($fileBase === null) {
            $logFailure('attachment_upload_failed', 'unique_filename_error');
            return ['error' => 'attachment_upload_failed'];
        }

        $targetPath = $dir . '/' . $fileBase . '.' . $nameExt;
        $category = self::extensionCategory($nameExt);
        $width = null;
        $height = null;

        if ($category === 'image') {
            // Validate image integrity and get dimensions
            $imageInfo = @getimagesize($tmpPath);
            if (!$imageInfo) {
                $logFailure('attachment_invalid_type', 'getimagesize_failed');
                return ['error' => 'attachment_invalid_type'];
            }

            $width = (int)($imageInfo[0] ?? 0);
            $height = (int)($imageInfo[1] ?? 0);
            if ($width <= 0 || $height <= 0 || $width > 10000 || $height > 10000) {
                $logFailure('attachment_invalid_type', 'dimensions:' . $width . 'x' . $height);
                return ['error' => 'attachment_invalid_type'];
            }

            // Re-encode through GD to strip any embedded malicious data
            $write = self::writeSanitizedImage($tmpPath, $mime, $targetPath);
            if (!$write) {
                $logFailure('attachment_upload_failed', 'write_sanitized_image:' . $targetPath);
                return ['error' => 'attachment_upload_failed'];
            }
        } else {
            // For non-image files: copy directly after MIME validation above
            if (!self::isPathWithinAttachmentsRoot($targetPath) || !copy($tmpPath, $targetPath)) {
                $logFailure('attachment_upload_failed', 'copy_failed:' . $targetPath);
                return ['error' => 'attachment_upload_failed'];
            }
        }

        @chmod($targetPath, 0644);
        clearstatcache(true, $targetPath);
        $storedSize = (int)@filesize($targetPath);
        if ($storedSize <= 0 || $storedSize > self::maxFileSizeBytes()) {
            self::safeUnlinkPath($targetPath);
            $logFailure('attachment_too_large', 'stored_size:' . $storedSize);
            return ['error' => 'attachment_too_large'];
        }

        Database::query(
            "INSERT INTO attachments (chat_id, user_id, original_name, file_name, file_extension, mime_type, file_size, width, height, file_hash, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
            [$chatId, $userId, $originalName, $fileBase, $nameExt, $mime, $storedSize, $width, $height, $fileHash]
        );

        $id = (int)Database::getInstance()->lastInsertId();

        if ($attachmentLogging) {
            ErrorHandler::logToDirectory('attachment.log', 'upload_success', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'original_name' => $originalName,
                'file_name' => $fileBase . '.' . $nameExt,
                'size' => $storedSize,
                'width' => $width,
                'height' => $height,
                'dedup' => false,
            ]);
        }

        return [
            'success' => true,
            'attachment' => [
                'id' => $id,
                'original_name' => $originalName,
                'file_name' => $fileBase,
                'file_extension' => $nameExt,
                'mime_type' => $mime,
                'file_size' => $storedSize,
                'width' => $width,
                'height' => $height,
                'url' => self::publicUrl($userNumber, $fileBase, $nameExt)
            ]
        ];
    }

    public static function deletePendingByIdForUser(int $attachmentId, int $userId): bool {
        $attachment = Database::query(
            "SELECT a.id, a.file_name, a.file_extension, a.dedup_source_id, u.user_number
             FROM attachments a
             JOIN users u ON u.id = a.user_id
             WHERE a.id = ? AND a.user_id = ? AND a.status = 'pending'
             LIMIT 1",
            [$attachmentId, $userId]
        )->fetch();

        if (!$attachment) {
            return false;
        }

        if ($attachment->dedup_source_id === null) {
            // This attachment owns a physical file; release it (promoting a referencing attachment if needed)
            $released = self::releaseOwnedFile(
                (string)$attachment->user_number,
                (string)$attachment->file_name,
                (string)$attachment->file_extension,
                $attachmentId
            );
            if (!$released) {
                return false;
            }
        }
        // Dedup references have no physical file of their own — nothing to delete from disk

        Database::query("DELETE FROM attachments WHERE id = ? AND user_id = ? AND status = 'pending'", [$attachmentId, $userId]);
        return true;
    }

    public static function markPendingSubmitted(int $chatId, int $userId, int $messageId, array $attachmentIds): void {
        if (count($attachmentIds) === 0) {
            return;
        }

        $safeIds = [];
        foreach ($attachmentIds as $id) {
            $number = (int)$id;
            if ($number > 0) {
                $safeIds[$number] = true;
            }
        }

        if (count($safeIds) === 0) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($safeIds), '?'));
        $params = array_merge([$messageId], [$chatId, $userId], array_keys($safeIds));

        Database::query(
            "UPDATE attachments
             SET status = 'submitted', message_id = ?, submitted_at = NOW()
             WHERE chat_id = ? AND user_id = ? AND status = 'pending' AND id IN ($placeholders)",
            $params
        );
    }

    public static function attachSubmittedToMessages(array &$messages): void {
        if (count($messages) === 0) {
            return;
        }

        $messageIds = [];
        foreach ($messages as $message) {
            $message->attachments = [];
            $id = (int)($message->id ?? 0);
            if ($id > 0) {
                $messageIds[$id] = true;
            }
        }

        if (count($messageIds) === 0) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        try {
            $rows = Database::query(
                "SELECT a.id, a.user_id, a.message_id, a.original_name, a.file_name, a.file_extension, a.mime_type, a.file_size, a.width, a.height, u.user_number
                 FROM attachments a
                 JOIN users u ON u.id = a.user_id
                 WHERE a.status = 'submitted' AND a.message_id IN ($placeholders)
                 ORDER BY a.id ASC",
                array_keys($messageIds)
            )->fetchAll();
        } catch (Throwable $e) {
            return;
        }

        $byMessage = [];
        foreach ($rows as $row) {
            $row->url = self::publicUrl((string)$row->user_number, (string)$row->file_name, (string)$row->file_extension);
            $byMessage[(int)$row->message_id][] = $row;
        }

        foreach ($messages as $message) {
            $message->attachments = $byMessage[(int)$message->id] ?? [];
        }
    }

    public static function cleanupPendingForUser($user): void {
        $userId = (int)($user->id ?? 0);
        $userNumber = preg_replace('/\D+/', '', (string)($user->user_number ?? ''));
        if ($userId <= 0 || !preg_match('/^\d{16}$/', $userNumber)) {
            return;
        }

        try {
            $pending = Database::query(
                "SELECT id, file_name, file_extension, dedup_source_id FROM attachments WHERE user_id = ? AND status = 'pending'",
                [$userId]
            )->fetchAll();
        } catch (Throwable $e) {
            return;
        }

        $deletingIds = [];
        foreach ($pending as $row) {
            $deletingIds[] = (int)$row->id;
        }

        $failedOwnerIds = [];
        foreach ($pending as $row) {
            if ($row->dedup_source_id !== null) {
                continue; // No physical file owned by this attachment
            }

            $released = self::releaseOwnedFile(
                $userNumber,
                (string)$row->file_name,
                (string)$row->file_extension,
                (int)$row->id,
                $deletingIds
            );
            if (!$released) {
                $failedOwnerIds[] = (int)$row->id;
                continue;
            }
        }

        if (count($failedOwnerIds) === 0) {
            Database::query("DELETE FROM attachments WHERE user_id = ? AND status = 'pending'", [$userId]);
        } else {
            $placeholders = implode(',', array_fill(0, count($failedOwnerIds), '?'));
            Database::query(
                "DELETE FROM attachments WHERE user_id = ? AND status = 'pending' AND id NOT IN ($placeholders)",
                array_merge([$userId], $failedOwnerIds)
            );
        }
    }

    public static function deleteFilesForChatId(int $chatId): bool {
        if ($chatId <= 0) {
            return false;
        }

        try {
            $rows = Database::query(
                "SELECT a.id, a.file_name, a.file_extension, a.dedup_source_id, u.user_number
                 FROM attachments a
                 JOIN users u ON u.id = a.user_id
                 WHERE a.chat_id = ?",
                [$chatId]
            )->fetchAll();
        } catch (Throwable $e) {
            return false;
        }

        $deletingIds = [];
        foreach ($rows as $row) {
            $deletingIds[] = (int)$row->id;
        }

        $allReleased = true;
        foreach ($rows as $row) {
            if ($row->dedup_source_id !== null) {
                continue; // No physical file owned by this attachment
            }

            $userNumber = preg_replace('/\D+/', '', (string)($row->user_number ?? ''));
            if (!preg_match('/^\d{16}$/', $userNumber)) {
                continue;
            }

            $released = self::releaseOwnedFile(
                $userNumber,
                (string)$row->file_name,
                (string)$row->file_extension,
                (int)$row->id,
                $deletingIds
            );
            if (!$released) {
                $allReleased = false;
                continue;
            }
        }

        return $allReleased;
    }

    /**
     * Release a physical file owned by an attachment being deleted.
     * If other attachments reference this file via dedup_source_id, the file is
     * copied to the first referencing attachment's location (promoting it to owner)
     * before the source file is removed. Any remaining references are redirected
     * to the new owner. Pass $excludeAttachmentIds to ignore referencing attachments
     * that are also being deleted in the same batch.
     */
    private static function releaseOwnedFile(string $userNumber, string $fileName, string $ext, int $attachmentId, array $excludeAttachmentIds = []): bool {
        if (!preg_match('/^\d{16}$/', $userNumber)) {
            return false;
        }
        if (!self::isSafeAttachmentFileBase($fileName) || !self::isSafeAttachmentExtension($ext)) {
            return false;
        }

        $excludeClause = '';
        $params = [$attachmentId];
        if (count($excludeAttachmentIds) > 0) {
            $excludeClause = ' AND a.id NOT IN (' . implode(',', array_fill(0, count($excludeAttachmentIds), '?')) . ')';
            $params = array_merge($params, $excludeAttachmentIds);
        }

        $referencing = Database::query(
            "SELECT a.id, a.file_name, a.file_extension, u.user_number
             FROM attachments a
             JOIN users u ON u.id = a.user_id
             WHERE a.dedup_source_id = ?" . $excludeClause . "
             LIMIT 1",
            $params
        )->fetch();

        $physicalPath = self::buildAttachmentPath($userNumber, $fileName, $ext);

        if (!$referencing) {
            self::safeUnlinkPath($physicalPath);
            return true;
        }

        // Promote the referencing attachment: copy the file to its directory so it becomes the new owner.
        // Keep old file intact if anything fails to avoid orphaning live references.
        $newOwnerNumber = preg_replace('/\D+/', '', (string)$referencing->user_number);
        if (!preg_match('/^\d{16}$/', $newOwnerNumber)) {
            return false;
        }

        $newOwnerFileName = (string)$referencing->file_name;
        $newOwnerExt = (string)$referencing->file_extension;
        if (!self::isSafeAttachmentFileBase($newOwnerFileName) || !self::isSafeAttachmentExtension($newOwnerExt)) {
            return false;
        }

        if (!is_file($physicalPath)) {
            return false;
        }

        $newOwnerDir = self::directoryForUserNumber($newOwnerNumber);
        if (!is_dir($newOwnerDir) && !@mkdir($newOwnerDir, 0755, true) && !is_dir($newOwnerDir)) {
            return false;
        }

        $newOwnerPath = self::buildAttachmentPath($newOwnerNumber, $newOwnerFileName, $newOwnerExt);
        $tmpOwnerPath = $newOwnerPath . '.tmp_' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if (!self::safeCopyWithinAttachmentsRoot($physicalPath, $tmpOwnerPath)) {
            return false;
        }

        if (!@rename($tmpOwnerPath, $newOwnerPath)) {
            self::safeUnlinkPath($tmpOwnerPath);
            return false;
        }

        @chmod($newOwnerPath, 0644);

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();

            Database::query(
                "SELECT id FROM attachments WHERE (id = ? OR dedup_source_id = ?)" . str_replace('a.id', 'id', $excludeClause) . " FOR UPDATE",
                array_merge([$attachmentId, $attachmentId], $excludeAttachmentIds)
            );

            Database::query(
                "UPDATE attachments SET dedup_source_id = NULL WHERE id = ? AND dedup_source_id = ?",
                [(int)$referencing->id, $attachmentId]
            );

            Database::query(
                "UPDATE attachments SET dedup_source_id = ? WHERE dedup_source_id = ? AND id <> ?",
                [(int)$referencing->id, $attachmentId, (int)$referencing->id]
            );

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::safeUnlinkPath($newOwnerPath);
            return false;
        }

        self::safeUnlinkPath($physicalPath);
        return true;
    }

    private static function normalizeOriginalName(string $name): ?string {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $base = basename($name);
        if ($base !== $name) {
            return null;
        }

        if (!preg_match('/^[A-Za-z0-9 _.-]+$/', $base)) {
            return null;
        }

        if (strpos($base, '..') !== false) {
            return null;
        }

        return $base;
    }

    private static function writeSanitizedImage(string $tmpPath, string $mime, string $targetPath): bool {
        if ($mime === 'image/jpeg') {
            if (!function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')) {
                return false;
            }
            $resource = @imagecreatefromjpeg($tmpPath);
            if (!$resource) {
                return false;
            }
            $ok = @imagejpeg($resource, $targetPath, 90);
            return (bool)$ok;
        }

        if ($mime === 'image/png') {
            if (!function_exists('imagecreatefrompng') || !function_exists('imagepng')) {
                return false;
            }
            $resource = @imagecreatefrompng($tmpPath);
            if (!$resource) {
                return false;
            }
            imagealphablending($resource, false);
            imagesavealpha($resource, true);
            $ok = @imagepng($resource, $targetPath, 6);
            return (bool)$ok;
        }

        if ($mime === 'image/webp') {
            if (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp')) {
                return false;
            }
            $resource = @imagecreatefromwebp($tmpPath);
            if (!$resource) {
                return false;
            }
            imagealphablending($resource, false);
            imagesavealpha($resource, true);
            $ok = @imagewebp($resource, $targetPath, 90);
            return (bool)$ok;
        }

        return false;
    }

    private static function directoryForUserNumber(string $userNumber): string {
        return self::storageBaseDirectory() . '/attachments/' . $userNumber;
    }

    private static function publicUrl(string $userNumber, string $fileName, string $extension): string {
        $userNumber = preg_replace('/\D+/', '', $userNumber);
        return base_url('/a/' . $userNumber . '/' . $fileName . '.' . $extension);
    }

    private static function generateUniqueFileBaseForUser(int $userId): ?string {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $base = str_pad((string)random_int(0, 9999999999999999), 16, '0', STR_PAD_LEFT);
            $exists = Database::query(
                "SELECT id FROM attachments WHERE user_id = ? AND file_name = ? LIMIT 1",
                [$userId, $base]
            )->fetch();
            if (!$exists) {
                return $base;
            }
        }

        return null;
    }

    private static function isSafeAttachmentFileBase(string $fileBase): bool {
        return (bool)preg_match('/^\d{16}$/', $fileBase);
    }

    private static function isSafeAttachmentExtension(string $ext): bool {
        $ext = strtolower(trim($ext));
        return isset(self::EXT_ALLOWED_MIMES[$ext]);
    }

    private static function safeUnlinkPath(string $path): void {
        if (!self::isPathWithinAttachmentsRoot($path)) {
            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function safeCopyWithinAttachmentsRoot(string $sourcePath, string $destinationPath): bool {
        if (!self::isPathWithinAttachmentsRoot($sourcePath) || !self::isPathWithinAttachmentsRoot($destinationPath)) {
            return false;
        }

        if (!is_file($sourcePath)) {
            return false;
        }

        return (bool)@copy($sourcePath, $destinationPath);
    }

    private static function isPathWithinAttachmentsRoot(string $path): bool {
        if ($path === '' || strpos($path, "\0") !== false) {
            return false;
        }

        $normalizedRoot = str_replace('\\', '/', rtrim(self::storageBaseDirectory(), '/')) . '/attachments/';
        $normalizedPath = str_replace('\\', '/', $path);

        return strncmp($normalizedPath, $normalizedRoot, strlen($normalizedRoot)) === 0;
    }

    private static function buildAttachmentPath(string $userNumber, string $fileBase, string $ext): string {
        return self::directoryForUserNumber($userNumber) . '/' . $fileBase . '.' . strtolower($ext);
    }

    private static function storageBaseDirectory(): string {
        if (defined('STORAGE_FILESYSTEM_ROOT')) {
            $configured = trim((string)STORAGE_FILESYSTEM_ROOT);
            if ($configured !== '') {
                return rtrim($configured, '/');
            }
        }

        return dirname(__DIR__, 3) . '/storage';
    }
}
