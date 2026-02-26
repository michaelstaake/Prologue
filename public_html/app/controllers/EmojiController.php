<?php
class EmojiController extends Controller {
    public function serve($params) {
        Auth::requireAuth();

        $filename = (string)($params['filename'] ?? '');

        if (!preg_match('/^[A-F0-9-]+\.svg$/i', $filename)) {
            ErrorHandler::abort(404, 'Not found');
        }

        $safeFilename = basename($filename);
        $emojiDirs = [];

        if (defined('STORAGE_FILESYSTEM_ROOT')) {
            $configuredStorageRoot = trim((string)STORAGE_FILESYSTEM_ROOT);
            if ($configuredStorageRoot !== '') {
                $emojiDirs[] = rtrim($configuredStorageRoot, '/') . '/emojis';
            }
        }

        $emojiDirs[] = dirname(__DIR__, 3) . '/storage/emojis';
        $emojiDirs[] = dirname(__DIR__, 2) . '/assets/emojis';
        $emojiDirs = array_values(array_unique($emojiDirs));

        $filePath = '';
        foreach ($emojiDirs as $emojiDir) {
            $candidate = rtrim($emojiDir, '/') . '/' . $safeFilename;
            if (is_file($candidate)) {
                $filePath = $candidate;
                break;
            }
        }

        if ($filePath === '') {
            ErrorHandler::abort(404, 'Not found');
        }

        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, max-age=31536000, immutable');
        readfile($filePath);
        exit;
    }
}
