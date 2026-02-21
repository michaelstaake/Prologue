<?php
class ErrorHandler {
    private static $debugMode = false;

    public static function setDebugMode($enabled) {
        self::$debugMode = (bool)$enabled;
    }

    public static function register() {
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        $logDir = trim((string)(getenv('LOG_DIRECTORY') ?: ''));
        if ($logDir === '' && defined('APP_LOG_DIRECTORY')) {
            $logDir = trim((string)APP_LOG_DIRECTORY);
        }
        if ($logDir !== '') {
            $logDir = rtrim($logDir, '/');
            if (is_dir($logDir) || @mkdir($logDir, 0775, true)) {
                ini_set('error_log', $logDir . '/error.log');
            }
        }
        error_reporting(E_ALL);

        set_exception_handler(function ($exception) {
            self::handleException($exception);
        });

        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(function () {
            $error = error_get_last();

            if (!$error) {
                return;
            }

            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (!in_array($error['type'], $fatalTypes, true)) {
                return;
            }

            self::logError('Fatal error', [
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);

            if (!headers_sent()) {
                @http_response_code(500);
            }

            self::render(500, $error['message'], [
                'file' => $error['file'],
                'line' => $error['line']
            ]);
            exit;
        });
    }

    public static function abort($status, $message = 'Unexpected error', $debug = []) {
        $status = (int) $status;

        self::logError("HTTP {$status}", [
            'message' => $message,
            'debug' => $debug,
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI'
        ]);

        if (!headers_sent()) {
            @http_response_code($status);
        }

        self::render($status, $message, $debug);
        exit;
    }

    private static function handleException($exception) {
        self::logError('Unhandled exception', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);

        if (!headers_sent()) {
            @http_response_code(500);
        }

        self::render(500, $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
        exit;
    }

    private static function render($status, $message, $debug = []) {
        if (self::isApiRequest()) {
            header('Content-Type: application/json');
            $payload = ['error' => self::statusLabel($status)];

            if (self::$debugMode) {
                $payload['debug'] = [
                    'message' => $message,
                    'details' => $debug
                ];
            }

            echo json_encode($payload);
            return;
        }

        $title = $status . ' - ' . self::statusLabel($status);
        $description = self::statusDescription($status);

        $debugBlock = '';
        if (self::$debugMode) {
            $details = [
                'Message: ' . $message
            ];

            if (!empty($debug['file'])) {
                $details[] = 'File: ' . $debug['file'];
            }

            if (!empty($debug['line'])) {
                $details[] = 'Line: ' . $debug['line'];
            }

            if (!empty($debug['trace'])) {
                $details[] = "Trace:\n" . $debug['trace'];
            }

            $debugBlock = '<pre style="margin-top:24px;padding:16px;border-radius:10px;background:#f4f6f8;color:#111827;text-align:left;overflow:auto;white-space:pre-wrap;">'
                . htmlspecialchars(implode("\n", $details), ENT_QUOTES, 'UTF-8')
                . '</pre>';
        }

        echo '<!doctype html>'
            . '<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
            . '<style>body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f9fafb;color:#111827;display:flex;min-height:100vh;align-items:center;justify-content:center}main{max-width:680px;margin:24px;padding:36px;background:#fff;border-radius:14px;box-shadow:0 12px 30px rgba(17,24,39,.08)}h1{margin:0 0 10px;font-size:32px}p{margin:0;color:#4b5563;line-height:1.5}.home-link{display:inline-block;margin-top:18px;color:#059669;text-decoration:none;font-weight:600}.home-link:hover{text-decoration:underline}</style>'
            . '</head><body><main>'
            . '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<a class="home-link" href="/">Back to home</a>'
            . $debugBlock
            . '</main></body></html>';
    }

    private static function logError($type, $context = []) {
        $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        error_log('[Prologue][' . $type . '] ' . $contextJson);
    }

    public static function logToDirectory(string $filename, string $type, array $context = []): void {
        $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $line = '[' . date('Y-m-d H:i:s') . '][Prologue][' . $type . '] ' . $contextJson . PHP_EOL;

        if (defined('APP_LOG_DIRECTORY')) {
            $dir = rtrim((string)APP_LOG_DIRECTORY, '/');
            if ($dir !== '' && (is_dir($dir) || @mkdir($dir, 0775, true))) {
                $path = $dir . '/' . $filename;
                if (file_put_contents($path, $line, FILE_APPEND | LOCK_EX) !== false) {
                    return;
                }
            }
        }

        error_log('[Prologue][' . $type . '][fallback:' . $filename . '] ' . $contextJson);
    }

    private static function isApiRequest() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/api') === 0) {
            return true;
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return stripos($accept, 'application/json') !== false;
    }

    private static function statusLabel($status) {
        switch ((int) $status) {
            case 403:
                return 'Forbidden';
            case 404:
                return 'Not Found';
            case 500:
                return 'Server Error';
            default:
                return 'Error';
        }
    }

    private static function statusDescription($status) {
        switch ((int) $status) {
            case 403:
                return 'You do not have permission to access this resource.';
            case 404:
                return 'The page you are looking for could not be found.';
            case 500:
                return 'Something went wrong on our side.';
            default:
                return 'An unexpected error occurred.';
        }
    }
}
