<?php
require_once __DIR__ . '/TOTP.php';

class TOTPTwoFAProvider implements TwoFAProviderInterface {
    public function getName(): string {
        return 'totp';
    }

    public function getLabel(): string {
        return 'Authenticator App';
    }

    public function getPriority(): int {
        return 20;
    }

    public function isAvailable(int $userId): bool {
        $row = Database::query(
            "SELECT id FROM totp_secrets WHERE user_id = ? AND confirmed_at IS NOT NULL",
            [$userId]
        )->fetch();
        return (bool)$row;
    }

    public function sendChallenge(int $userId, string $ip): bool {
        // No-op: TOTP codes are generated client-side by the authenticator app
        return true;
    }

    public function verifyCode(int $userId, string $code, string $ip): bool {
        $row = Database::query(
            "SELECT secret_encrypted FROM totp_secrets WHERE user_id = ? AND confirmed_at IS NOT NULL",
            [$userId]
        )->fetch();

        if (!$row) {
            return false;
        }

        $secret = TOTP::decrypt($row->secret_encrypted);
        if ($secret === null) {
            return false;
        }

        // Try as a TOTP code first
        if (preg_match('/^\d{6}$/', $code) && TOTP::verify($secret, $code, 1)) {
            return true;
        }

        // Then try as a recovery code
        return $this->verifyRecoveryCode($userId, $code);
    }

    public function cleanup(int $userId): void {
        // No temporary state to clean up for TOTP
    }

    private function verifyRecoveryCode(int $userId, string $code): bool {
        $codes = Database::query(
            "SELECT id, code_hash FROM totp_recovery_codes WHERE user_id = ? AND used_at IS NULL",
            [$userId]
        )->fetchAll();

        foreach ($codes as $row) {
            if (hash_equals($row->code_hash, hash('sha256', strtolower($code)))) {
                Database::query(
                    "UPDATE totp_recovery_codes SET used_at = NOW() WHERE id = ?",
                    [$row->id]
                );
                return true;
            }
        }

        return false;
    }
}
