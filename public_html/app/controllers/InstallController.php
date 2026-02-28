<?php

class InstallController extends Controller {
    public function showInstall() {
        $connection = $this->databaseConnectionStatus();
        if (!$connection['ok']) {
            $this->view('install', [
                'canInstall' => false,
                'errorMessage' => $connection['error']
            ]);
            return;
        }

        if (!$this->isDatabaseEmpty()) {
            ErrorHandler::abort(404);
        }

        $this->view('install', [
            'canInstall' => true,
            'errorMessage' => ''
        ]);
    }

    public function install() {
        Auth::csrfValidate();

        $connection = $this->databaseConnectionStatus();
        if (!$connection['ok']) {
            $this->view('install', [
                'canInstall' => false,
                'errorMessage' => $connection['error']
            ]);
            return;
        }

        if (!$this->isDatabaseEmpty()) {
            ErrorHandler::abort(404);
        }

        $username = User::normalizeUsername($_POST['username'] ?? '');
        $email = trim((string)($_POST['email'] ?? ''));
        $passwordRaw = (string)($_POST['password'] ?? '');

        if (!User::isUsernameFormatValid($username)) {
            $this->view('install', [
                'canInstall' => true,
                'errorMessage' => 'Invalid username format. Use 4-32 lowercase letters/numbers, starting with a letter.',
                'inputUsername' => $username,
                'inputEmail' => $email
            ]);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->view('install', [
                'canInstall' => true,
                'errorMessage' => 'Invalid email address.',
                'inputUsername' => $username,
                'inputEmail' => $email
            ]);
            return;
        }

        if (strlen($passwordRaw) < 8) {
            $this->view('install', [
                'canInstall' => true,
                'errorMessage' => 'Password must be at least 8 characters long.',
                'inputUsername' => $username,
                'inputEmail' => $email
            ]);
            return;
        }

        try {
            $this->executeDatabaseSchema();
            Setting::set('database_version', APP_VERSION);

            $passwordHash = password_hash($passwordRaw, PASSWORD_DEFAULT);
            $userNumber = $this->generateUserNumber();

            Database::query(
                "INSERT INTO users (username, email, password, user_number, email_verified_at, role) VALUES (?, ?, ?, ?, NOW(), 'admin')",
                [$username, $email, $passwordHash, $userNumber]
            );

            $userId = (int)Database::getInstance()->lastInsertId();
            Database::query("INSERT INTO username_history (user_id, username) VALUES (?, ?)", [$userId, $username]);
        } catch (Throwable $exception) {
            $this->view('install', [
                'canInstall' => false,
                'errorMessage' => 'Install failed: ' . $exception->getMessage(),
                'inputUsername' => $username,
                'inputEmail' => $email
            ]);
            return;
        }

        $this->flash('success', 'installed');
        $this->redirect('/login');
    }

    private function databaseConnectionStatus() {
        try {
            Database::getInstance();
            return ['ok' => true, 'error' => ''];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => 'Database connection failed: ' . $exception->getMessage()];
        }
    }

    private function isDatabaseEmpty() {
        $tables = Database::query('SHOW TABLES')->fetchAll();
        return count($tables) === 0;
    }

    private function executeDatabaseSchema() {
        $schemaSql = defined('DATABASE_SCHEMA_SQL') ? (string)DATABASE_SCHEMA_SQL : '';

        if (trim($schemaSql) === '') {
            throw new RuntimeException('Could not read database schema from DATABASE_SCHEMA_SQL.');
        }

        $sql = $schemaSql;
        $schemaSource = 'DATABASE_SCHEMA_SQL';

        $this->applyInstallDatabaseDefaults();

        $schemaSection = explode('/* STEP 2 */', $sql, 2)[0];
        $statements = $this->splitSqlStatements($schemaSection);

        foreach ($statements as $index => $statement) {
            if (preg_match('/^CREATE\s+DATABASE\b/i', $statement)) {
                continue;
            }

            if (preg_match('/^USE\s+/i', $statement)) {
                continue;
            }

            try {
                Database::query($statement);
            } catch (Throwable $exception) {
                $preview = preg_replace('/\s+/', ' ', trim($statement));
                if ($preview === null) {
                    $preview = '';
                }
                $preview = mb_substr($preview, 0, 180);
                throw new RuntimeException(
                    'Schema execution failed at statement #' . ($index + 1) . ' from ' . $schemaSource . ': ' . $preview . ' | ' . $exception->getMessage(),
                    0,
                    $exception
                );
            }
        }

        $this->assertRequiredInstallTables();
    }

    private function applyInstallDatabaseDefaults() {
        $defaults = [
            "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            "SET SESSION default_storage_engine = InnoDB"
        ];

        foreach ($defaults as $statement) {
            try {
                Database::query($statement);
            } catch (Throwable $exception) {
            }
        }
    }

    private function assertRequiredInstallTables() {
        $requiredTables = [
            'settings',
            'users',
            'auth_attempt_limits'
        ];

        $missingTables = [];
        foreach ($requiredTables as $tableName) {
            $row = Database::query(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1',
                [DB_NAME, $tableName]
            )->fetch();

            if (!$row) {
                $missingTables[] = $tableName;
            }
        }

        if (!empty($missingTables)) {
            throw new RuntimeException('Install incomplete: missing required tables: ' . implode(', ', $missingTables));
        }
    }

    private function splitSqlStatements($sql) {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $next = ($index + 1 < $length) ? $sql[$index + 1] : '';
            $previous = ($index > 0) ? $sql[$index - 1] : '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                }
                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $index++;
                }
                continue;
            }

            if (!$inSingleQuote && !$inDoubleQuote) {
                if ($char === '-' && $next === '-') {
                    $third = ($index + 2 < $length) ? $sql[$index + 2] : '';
                    if ($third === '' || ctype_space($third)) {
                        $inLineComment = true;
                        $index++;
                        continue;
                    }
                }

                if ($char === '#') {
                    $inLineComment = true;
                    continue;
                }

                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $index++;
                    continue;
                }
            }

            if ($char === "'" && !$inDoubleQuote && $previous !== '\\') {
                $inSingleQuote = !$inSingleQuote;
            } elseif ($char === '"' && !$inSingleQuote && $previous !== '\\') {
                $inDoubleQuote = !$inDoubleQuote;
            }

            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }

    private function generateUserNumber() {
        do {
            $userNumber = str_pad((string) random_int(0, 9999999999999999), 16, '0', STR_PAD_LEFT);
            $exists = Database::query('SELECT id FROM users WHERE user_number = ? LIMIT 1', [$userNumber])->fetch();
        } while ($exists);

        return $userNumber;
    }
}