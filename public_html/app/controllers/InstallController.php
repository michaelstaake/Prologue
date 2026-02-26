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
        $schemaPath = defined('DATABASE_SCHEMA_FILE') ? (string)DATABASE_SCHEMA_FILE : (dirname(__DIR__, 4) . '/database.sql');
        $sql = @file_get_contents($schemaPath);
        if ($sql === false) {
            throw new RuntimeException('Could not read database schema file.');
        }

        $schemaSection = explode('/* STEP 2 */', $sql, 2)[0];
        $statements = $this->splitSqlStatements($schemaSection);

        foreach ($statements as $statement) {
            if (preg_match('/^CREATE\s+DATABASE\b/i', $statement)) {
                continue;
            }

            if (preg_match('/^USE\s+/i', $statement)) {
                continue;
            }

            Database::query($statement);
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