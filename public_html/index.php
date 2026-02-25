<?php
date_default_timezone_set('UTC');

function is_https_request(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }

    $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($forwardedProto !== '') {
        return in_array('https', array_map('trim', explode(',', $forwardedProto)), true);
    }

    return false;
}

function is_local_host_request(): bool {
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === '') {
        return false;
    }

    $host = preg_replace('/:\\d+$/', '', $host);

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function enforce_https_if_needed(): void {
    if (PHP_SAPI === 'cli' || is_https_request()) {
        return;
    }

    $enforceHttps = getenv('SECURITY_ENFORCE_HTTPS');
    if ($enforceHttps === false) {
        $enforceHttps = '1';
    }

    if ($enforceHttps !== '1' || is_local_host_request()) {
        return;
    }

    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return;
    }

    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

function configure_secure_session(): void {
    $isHttps = is_https_request();

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function send_security_headers(): void {
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), payment=(), usb=(), camera=(self), microphone=(self)');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self' data: https://cdnjs.cloudflare.com; connect-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com");

    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

enforce_https_if_needed();
configure_secure_session();
session_start();
require_once __DIR__ . '/app/config/config.php';

function flash_get(string $key): ?string {
    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }
    $value = (string)$_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $value;
}

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/app/core/' . $class . '.php',
        __DIR__ . '/app/controllers/' . $class . '.php',
        __DIR__ . '/app/models/' . $class . '.php'
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

ErrorHandler::register();
send_security_headers();

try {
    $databaseVersion = Setting::get('database_version');
    if ($databaseVersion === null || (string)$databaseVersion !== APP_VERSION) {
        Setting::set('database_version', APP_VERSION);
    }
    ErrorHandler::setDebugMode(Setting::get('error_display') === '1');


} catch (Throwable $exception) {
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (strpos($requestPath, '/api') === 0) {
    RateLimiter::enforceApiLimit($requestPath, $_SERVER['REQUEST_METHOD'] ?? 'GET');
}

$router = new Router();

// Auth
$router->get('/install', 'InstallController@showInstall');
$router->post('/install', 'InstallController@install');

$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/2fa', 'AuthController@show2FA');
$router->post('/2fa', 'AuthController@verify2FA');
$router->get('/verify-email', 'AuthController@showVerifyEmail');
$router->post('/verify-email', 'AuthController@verifyEmail');
$router->post('/verify-email/resend', 'AuthController@resendVerifyEmail');
$router->get('/register', 'AuthController@showRegister');
$router->post('/register', 'AuthController@register');
$router->get('/logout', 'AuthController@logout');
$router->get('/forgot-password', 'AuthController@showForgot');
$router->post('/forgot-password', 'AuthController@forgotPassword');
$router->get('/reset-password', 'AuthController@showReset');
$router->post('/reset-password', 'AuthController@resetPassword');

// Main
$router->get('/', 'HomeController@index');
$router->get('/posts', 'HomeController@posts');
$router->get('/search', 'HomeController@search');

// User
$router->get('/u/{user_number}', 'UserController@profile');

// Admin users
$router->get('/users', 'AdminUserController@index');
$router->post('/users/change-group', 'AdminUserController@changeGroup');
$router->post('/users/ban', 'AdminUserController@ban');
$router->post('/users/delete', 'AdminUserController@delete');
$router->get('/tree', 'TreeController@index');

// Friends
$router->post('/friends/request', 'FriendController@sendRequest');
$router->post('/friends/accept', 'FriendController@acceptRequest');
$router->post('/friends/cancel', 'FriendController@cancelRequest');
$router->post('/friends/unfriend', 'FriendController@unfriend');
$router->post('/friends/favorite', 'FriendController@toggleFavorite');

// Attachments
$router->get('/a/{user_number}/{filename}', 'AttachmentController@serve');

// Avatars
$router->get('/avatars/{filename}', 'AvatarController@serve');

// Emojis
$router->get('/emojis/{filename}', 'EmojiController@serve');

// Chat
$router->get('/c/{chat_number}', 'ChatController@show');
$router->post('/api/messages', 'ChatController@sendMessage'); // also used by web
$router->post('/api/messages/react', 'ChatController@reactMessage');
$router->post('/api/posts', 'PostController@create');
$router->post('/api/posts/react', 'PostController@react');
$router->post('/api/posts/delete', 'PostController@delete');
$router->post('/api/attachments/upload', 'ChatController@uploadAttachment');
$router->post('/api/attachments/delete', 'ChatController@deleteAttachment');
$router->post('/api/chats/typing', 'ApiController@updateTyping');
$router->post('/api/chats/group/create', 'ChatController@createGroup');
$router->post('/api/chats/group/add-member', 'ChatController@addGroupMember');
$router->post('/api/chats/group/remove-member', 'ChatController@removeGroupMember');
$router->post('/api/chats/group/leave', 'ChatController@leaveGroup');
$router->post('/api/chats/group/delete', 'ChatController@deleteGroup');
$router->post('/api/chats/rename', 'ChatController@renameChat');
$router->post('/api/status', 'ApiController@updateStatus');

// Calls
$router->post('/api/calls/start', 'CallController@startCall');
$router->post('/api/calls/decline', 'CallController@declineCall');
$router->post('/api/calls/signal', 'CallController@signal');
$router->post('/api/calls/end', 'CallController@endCall');

// Notifications
$router->get('/notifications', 'NotificationController@getAll');
$router->post('/api/notifications/seen', 'NotificationController@markSeen');
$router->post('/api/notifications/sidebar-state', 'NotificationController@updateSidebarState');
$router->post('/api/notifications/read', 'NotificationController@markRead');

// Reports
$router->get('/reports', 'ReportController@index');
$router->post('/reports/mark-handled', 'ReportController@markHandled');
$router->post('/api/report', 'ReportController@submit');

// Trash (admin only)
$router->get('/trash', 'TrashController@index');
$router->get('/trash/{chat_number}', 'TrashController@show');
$router->post('/trash/delete', 'TrashController@delete');

// Config (admin only)
$router->get('/config', 'ConfigController@index');
$router->post('/config/mail', 'ConfigController@saveMailSettings');
$router->post('/config/mail/test', 'ConfigController@sendTestMail');
$router->post('/config/accounts', 'ConfigController@saveAccountSettings');
$router->post('/config/attachments', 'ConfigController@saveAttachmentSettings');
$router->post('/config/more', 'ConfigController@saveMoreSettings');

// Settings
$router->get('/settings', 'HomeController@settings');
$router->get('/info', 'HomeController@info');
$router->post('/settings/account/email', 'HomeController@saveAccountEmail');
$router->post('/settings/account/email/verify', 'HomeController@verifyAccountEmailChange');
$router->post('/settings/account/password', 'HomeController@saveAccountPassword');
$router->post('/settings/profile/username', 'HomeController@saveProfileUsername');
$router->post('/settings/avatar', 'HomeController@saveAvatarSettings');
$router->post('/settings/avatar/delete', 'HomeController@deleteAvatarSettings');
$router->post('/settings/notifications', 'HomeController@saveNotificationSettings');
$router->post('/settings/timezone', 'HomeController@saveTimezoneSettings');
$router->post('/settings/invites/generate', 'HomeController@generateInvite');
$router->post('/settings/invites/delete', 'HomeController@deleteInvite');
$router->post('/settings/sessions/exit', 'HomeController@exitSession');

// API routes (JSON only)
$router->group('/api', function($r) {
    $r->get('/users/search', 'ApiController@searchUsers');
    $r->get('/friends', 'ApiController@getFriends');
    $r->get('/chats', 'ApiController@getChats');
    $r->get('/messages/search', 'ApiController@searchMessages');
    $r->get('/messages/{chat_id}', 'ApiController@getMessages');
    $r->get('/posts/search', 'ApiController@searchPosts');
    $r->get('/notifications', 'ApiController@getNotifications');
    $r->get('/invites', 'ApiController@getInvites');
    $r->get('/calls/active/{chat_id}', 'ApiController@getActiveCall');
    $r->get('/calls/current', 'ApiController@getCurrentActiveCall');
    $r->get('/calls/signal/{call_id}', 'ApiController@getCallSignal');
    $r->get('/servers', 'ApiController@getServers');
    $r->get('/channels/{server_id}', 'ApiController@getChannels');
});

// Dispatch
$router->dispatch();