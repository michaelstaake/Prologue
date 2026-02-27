<?php

class UpdateChecker {
    private const GITHUB_LATEST_RELEASE_API_URL = 'https://api.github.com/repos/michaelstaake/Prologue/releases/latest';
    private const GITHUB_RELEASES_PAGE_URL = 'https://github.com/michaelstaake/Prologue/releases';

    public static function checkForAdminUser(int $userId, bool $ignoreSetting = false): array {
        if (!$ignoreSetting && (string)(Setting::get('check_for_updates') ?? '0') !== '1') {
            return ['status' => 'disabled'];
        }

        $user = User::find($userId);
        if (!$user || strtolower((string)($user->role ?? '')) !== 'admin') {
            return ['status' => 'not_admin'];
        }

        $latestVersion = self::fetchLatestGithubReleaseVersion();
        if ($latestVersion === null || $latestVersion === '') {
            return ['status' => 'unavailable'];
        }

        $currentVersion = self::normalizeVersionString((string)APP_VERSION);
        if ($latestVersion === $currentVersion) {
            return ['status' => 'up_to_date', 'latest_version' => $latestVersion];
        }

        $title = 'Update Available';
        $message = 'A newer Prologue release is available: ' . $latestVersion . '. You are currently running ' . APP_VERSION . '.';

        $alreadyExists = (int)Database::query(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'report' AND title = ? AND message = ? AND link = ?",
            [$userId, $title, $message, self::GITHUB_RELEASES_PAGE_URL]
        )->fetchColumn() > 0;

        if (!$alreadyExists) {
            Notification::create(
                $userId,
                'report',
                $title,
                $message,
                self::GITHUB_RELEASES_PAGE_URL
            );
        }

        return [
            'status' => 'update_available',
            'latest_version' => $latestVersion,
            'notification_created' => !$alreadyExists,
        ];
    }

    private static function fetchLatestGithubReleaseVersion(): ?string {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 4,
                'header' => "User-Agent: Prologue\r\nAccept: application/vnd.github+json\r\n",
            ],
        ]);

        $response = @file_get_contents(self::GITHUB_LATEST_RELEASE_API_URL, false, $context);
        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return null;
        }

        $rawVersion = trim((string)($decoded['tag_name'] ?? ''));
        if ($rawVersion === '') {
            return null;
        }

        return self::normalizeVersionString($rawVersion);
    }

    private static function normalizeVersionString(string $version): string {
        return strtolower(ltrim(trim($version), 'v'));
    }
}