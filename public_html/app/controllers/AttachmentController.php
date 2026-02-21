<?php
class AttachmentController extends Controller {
    public function serve($params) {
        Auth::requireAuth();

        $userNumber = (string)($params['user_number'] ?? '');
        $filename   = (string)($params['filename'] ?? '');

        if (!preg_match('/^\d{16}$/', $userNumber)) {
            ErrorHandler::abort(404, 'Not found');
        }

        if (!preg_match('/^\d{16}\.(jpg|png)$/', $filename, $m)) {
            ErrorHandler::abort(404, 'Not found');
        }

        $storageBase = defined('STORAGE_FILESYSTEM_ROOT')
            ? rtrim((string)STORAGE_FILESYSTEM_ROOT, '/')
            : dirname(__DIR__, 3) . '/storage';

        $filePath = $storageBase . '/attachments/' . $userNumber . '/' . $filename;

        if (!is_file($filePath)) {
            ErrorHandler::abort(404, 'Not found');
        }

        $contentType = $m[1] === 'png' ? 'image/png' : 'image/jpeg';

        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, max-age=31536000, immutable');
        readfile($filePath);
        exit;
    }
}
