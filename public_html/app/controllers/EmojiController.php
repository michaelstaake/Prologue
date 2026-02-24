<?php
class EmojiController extends Controller {
    public function serve($params) {
        Auth::requireAuth();

        $filename = (string)($params['filename'] ?? '');

        if (!preg_match('/^[A-F0-9-]+\.svg$/i', $filename)) {
            ErrorHandler::abort(404, 'Not found');
        }

        $storageBase = defined('STORAGE_FILESYSTEM_ROOT')
            ? rtrim((string)STORAGE_FILESYSTEM_ROOT, '/')
            : dirname(__DIR__, 3) . '/storage';

        $filePath = $storageBase . '/emojis/' . basename($filename);

        if (!is_file($filePath)) {
            ErrorHandler::abort(404, 'Not found');
        }

        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, max-age=31536000, immutable');
        readfile($filePath);
        exit;
    }
}
