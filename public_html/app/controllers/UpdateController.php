<?php

class UpdateController extends Controller {

    private const UPDATE_LOCK_TTL_SECONDS = 120;
    private const UPDATE_LOCK_FILENAME = 'db_update.lock';

    /**
     * Map of version => SQL statements to run when migrating TO that version.
     * Add entries here for each new version that requires schema changes.
     * Versions below 0.0.3 are intentionally empty — no installs exist at those versions.
     */
    private static function getMigrations(): array {
        return [
            // Example for a future version:
            // '0.0.4' => [
            //     "ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER email",
            // ],
        ];
    }

    private function getUpdateLockPath(): string {
        return rtrim((string)APP_LOG_DIRECTORY, '/') . '/' . self::UPDATE_LOCK_FILENAME;
    }

    private function getRemainingLockSeconds(): int {
        $path = $this->getUpdateLockPath();
        if (!is_file($path)) {
            return 0;
        }

        $startedAt = (int)trim((string)@file_get_contents($path));
        if ($startedAt <= 0) {
            return 0;
        }

        $elapsed = time() - $startedAt;
        if ($elapsed >= self::UPDATE_LOCK_TTL_SECONDS) {
            return 0;
        }

        return self::UPDATE_LOCK_TTL_SECONDS - $elapsed;
    }

    private function tryAcquireUpdateLock(): int {
        $logDirectory = rtrim((string)APP_LOG_DIRECTORY, '/');
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0755, true);
        }

        $path = $this->getUpdateLockPath();
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            return 0;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return 0;
            }

            rewind($handle);
            $existingValue = stream_get_contents($handle);
            $startedAt = (int)trim((string)$existingValue);

            if ($startedAt > 0) {
                $elapsed = time() - $startedAt;
                if ($elapsed < self::UPDATE_LOCK_TTL_SECONDS) {
                    return self::UPDATE_LOCK_TTL_SECONDS - $elapsed;
                }
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string)time());
            fflush($handle);

            return 0;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function renderUpdateView(string $dbVersion, ?string $errorMessage = null): void {
        $lockRemaining = $this->getRemainingLockSeconds();

        $this->viewRaw('update', [
            'csrf'          => $this->csrfToken(),
            'dbVersion'     => $dbVersion,
            'appVersion'    => APP_VERSION,
            'errorMessage'  => $errorMessage,
            'lockRemaining' => $lockRemaining,
        ]);
    }

    public function showUpdate() {
        $dbVersion = Setting::get('database_version') ?? '0.0.0';

        if (version_compare($dbVersion, APP_VERSION, '>=')) {
            ErrorHandler::abort(404);
        }

        $this->renderUpdateView($dbVersion);
    }

    public function runUpdate() {
        Auth::csrfValidate();

        $dbVersion = Setting::get('database_version') ?? '0.0.0';

        if (version_compare($dbVersion, APP_VERSION, '>=')) {
            ErrorHandler::abort(404);
        }

        $lockRemaining = $this->tryAcquireUpdateLock();
        if ($lockRemaining > 0) {
            $this->renderUpdateView(
                $dbVersion,
                'Another update attempt is already in progress. Please try again in about ' . $lockRemaining . ' seconds.'
            );
            return;
        }

        $migrations = self::getMigrations();
        $versions = array_keys($migrations);
        usort($versions, 'version_compare');

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            foreach ($versions as $version) {
                if (version_compare($version, APP_VERSION, '>')) {
                    continue;
                }

                if (version_compare($dbVersion, $version, '<')) {
                    foreach ($migrations[$version] as $sql) {
                        Database::query($sql);
                    }
                    Setting::set('database_version', $version);
                    $dbVersion = $version;
                }
            }

            // Advance DB version to app version even if no schema migrations were needed
            if (version_compare($dbVersion, APP_VERSION, '<')) {
                Setting::set('database_version', APP_VERSION);
            }

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->renderUpdateView($dbVersion, 'Update failed at version ' . $dbVersion . ': ' . $e->getMessage());
            return;
        }

        $this->flash('success', 'update_complete');
        $this->redirect('/');
    }
}
