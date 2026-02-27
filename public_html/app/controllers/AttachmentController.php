<?php
class AttachmentController extends Controller {
    private const EXT_CONTENT_TYPE = [
        'jpg'  => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'pdf'  => 'application/pdf',
        'odt'  => 'application/vnd.oasis.opendocument.text',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'zip'  => 'application/zip',
        '7z'   => 'application/x-7z-compressed',
    ];

    // Extensions that must be downloaded rather than rendered inline
    private const DOWNLOAD_EXTENSIONS = ['mp4', 'webm', 'pdf', 'odt', 'doc', 'docx', 'zip', '7z'];

    public function serve($params) {
        Auth::requireAuth();

        $userNumber = (string)($params['user_number'] ?? '');
        $filename   = (string)($params['filename'] ?? '');

        if (!preg_match('/^\d{16}$/', $userNumber)) {
            ErrorHandler::abort(404, 'Not found');
        }

        if (!preg_match('/^\d{16}\.(jpg|png|webp|mp4|webm|pdf|odt|doc|docx|zip|7z)$/', $filename, $m)) {
            ErrorHandler::abort(404, 'Not found');
        }

        $storageBase = defined('STORAGE_FILESYSTEM_ROOT')
            ? rtrim((string)STORAGE_FILESYSTEM_ROOT, '/')
            : dirname(__DIR__, 3) . '/storage';

        $filePath = $storageBase . '/attachments/' . $userNumber . '/' . $filename;

        if (!is_file($filePath)) {
            ErrorHandler::abort(404, 'Not found');
        }

        $ext = $m[1];
        $contentType = self::EXT_CONTENT_TYPE[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, max-age=31536000, immutable');

        if (in_array($ext, self::DOWNLOAD_EXTENSIONS, true)) {
            $fileBase = substr($filename, 0, strlen($filename) - strlen($ext) - 1);
            $record = Database::query(
                "SELECT original_name FROM attachments WHERE file_name = ? AND file_extension = ? LIMIT 1",
                [$fileBase, $ext]
            )->fetch();
            $downloadName = ($record && (string)$record->original_name !== '')
                ? preg_replace('/[^A-Za-z0-9 _.-]/', '_', (string)$record->original_name)
                : preg_replace('/[^A-Za-z0-9 _.-]/', '_', $filename);
            header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        }

        readfile($filePath);
        exit;
    }
}
