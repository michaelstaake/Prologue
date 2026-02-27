<?php

class UpdateController extends Controller {

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

    public function showUpdate() {
        $dbVersion = Setting::get('database_version') ?? '0.0.0';

        if (version_compare($dbVersion, APP_VERSION, '>=')) {
            ErrorHandler::abort(404);
        }

        $this->viewRaw('update', [
            'csrf'       => $this->csrfToken(),
            'dbVersion'  => $dbVersion,
            'appVersion' => APP_VERSION,
        ]);
    }

    public function runUpdate() {
        Auth::csrfValidate();

        $dbVersion = Setting::get('database_version') ?? '0.0.0';

        if (version_compare($dbVersion, APP_VERSION, '>=')) {
            ErrorHandler::abort(404);
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

            $this->viewRaw('update', [
                'csrf'         => $this->csrfToken(),
                'dbVersion'    => $dbVersion,
                'appVersion'   => APP_VERSION,
                'errorMessage' => 'Update failed at version ' . $dbVersion . ': ' . $e->getMessage(),
            ]);
            return;
        }

        $this->flash('success', 'update_complete');
        $this->redirect('/');
    }
}
