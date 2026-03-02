<?php

class TOTP {
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const ALGORITHM = 'sha1';
    private const SECRET_BYTES = 20;
    private const CIPHER = 'aes-256-gcm';

    /**
     * Generate a random base32-encoded secret.
     */
    public static function generateSecret(): string {
        return self::base32Encode(random_bytes(self::SECRET_BYTES));
    }

    /**
     * Generate the TOTP code for a given secret and timestamp.
     */
    public static function getCode(string $base32Secret, ?int $timestamp = null): string {
        $timestamp = $timestamp ?? time();
        $counter = (int)floor($timestamp / self::PERIOD);

        $secret = self::base32Decode($base32Secret);
        $counterBytes = pack('J', $counter); // 64-bit big-endian

        $hash = hash_hmac(self::ALGORITHM, $counterBytes, $secret, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string)$code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a code with a time window tolerance.
     */
    public static function verify(string $base32Secret, string $code, int $window = 1): bool {
        $now = time();
        for ($i = -$window; $i <= $window; $i++) {
            $timestamp = $now + ($i * self::PERIOD);
            if (hash_equals(self::getCode($base32Secret, $timestamp), $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build the otpauth:// URI for QR code provisioning.
     */
    public static function getProvisioningUri(string $base32Secret, string $accountName, string $issuer): string {
        $label = rawurlencode($issuer) . ':' . rawurlencode($accountName);
        $params = http_build_query([
            'secret' => $base32Secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);
        return 'otpauth://totp/' . $label . '?' . $params;
    }

    /**
     * Encrypt a TOTP secret for database storage.
     */
    public static function encrypt(string $plaintext): string {
        $key = hash('sha256', CSRF_SECRET, true);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed');
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt a TOTP secret from database storage.
     */
    public static function decrypt(string $encoded): ?string {
        $key = hash('sha256', CSRF_SECRET, true);
        $data = base64_decode($encoded, true);
        if ($data === false || strlen($data) < 28) {
            return null;
        }
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plaintext === false ? null : $plaintext;
    }

    /**
     * Generate recovery codes.
     */
    public static function generateRecoveryCodes(int $count = 8): array {
        $codes = [];
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $len = strlen($chars) - 1;
        for ($i = 0; $i < $count; $i++) {
            $code = '';
            for ($j = 0; $j < 8; $j++) {
                $code .= $chars[random_int(0, $len)];
            }
            $codes[] = $code;
        }
        return $codes;
    }

    private static function base32Encode(string $data): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= $alphabet[bindec($chunk)];
        }
        return $encoded;
    }

    private static function base32Decode(string $encoded): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = strtoupper(rtrim($encoded, '='));
        $binary = '';
        foreach (str_split($encoded) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) continue;
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $decoded = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) < 8) break;
            $decoded .= chr(bindec($byte));
        }
        return $decoded;
    }
}
