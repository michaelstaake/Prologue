<?php
class WebPushService {
    private const PUSH_TTL_SECONDS = 60;

    public static function isConfigured(): bool {
        return self::getVapidPublicKey() !== '' && self::getVapidPrivateKeyPem() !== null;
    }

    public static function getVapidPublicKey(): string {
        $envKey = trim((string)(getenv('PUSH_VAPID_PUBLIC_KEY') ?: ''));
        if ($envKey !== '') {
            return $envKey;
        }

        $settingKey = trim((string)(Setting::get('push_vapid_public_key') ?? ''));
        return $settingKey;
    }

    public static function sendForNotification($userId, $type, $title, $message, $link = null): void {
        $userId = (int)$userId;
        if ($userId <= 0 || !self::isConfigured()) {
            return;
        }

        $normalizedType = strtolower(trim((string)$type));
        // Send Web Push for message and call poke notifications.
        if (!in_array($normalizedType, ['message', 'poke'], true)) {
            return;
        }

        $isEnabled = (string)(Setting::get('web_push_notifications_' . $userId) ?? '0') === '1';
        if (!$isEnabled) {
            return;
        }

        $subscriptions = PushSubscription::getActiveForUser($userId);
        if (count($subscriptions) === 0) {
            return;
        }

        foreach ($subscriptions as $subscription) {
            $subscriptionId = (int)($subscription->id ?? 0);
            $endpoint = trim((string)($subscription->endpoint ?? ''));
            if ($subscriptionId <= 0 || $endpoint === '') {
                continue;
            }

            $result = self::sendEndpoint($endpoint);
            if ($result['ok']) {
                PushSubscription::markSuccess($subscriptionId);
                continue;
            }

            $statusCode = $result['status'];
            $errorMessage = $result['error'];

            if ($statusCode === 404 || $statusCode === 410) {
                PushSubscription::markPermanentFailure($subscriptionId, $statusCode, $errorMessage);
                PushSubscription::removeById($subscriptionId);
                continue;
            }

            if ($statusCode >= 400 && $statusCode < 500) {
                PushSubscription::markPermanentFailure($subscriptionId, $statusCode, $errorMessage);
                continue;
            }

            PushSubscription::markRetryableFailure($subscriptionId, $statusCode > 0 ? $statusCode : null, $errorMessage);
        }
    }

    private static function sendEndpoint(string $endpoint): array {
        $audience = self::getAudienceFromEndpoint($endpoint);
        if ($audience === null) {
            return [
                'ok' => false,
                'status' => 0,
                'error' => 'Invalid push endpoint audience'
            ];
        }

        $jwt = self::buildVapidJwt($audience);
        if ($jwt === null) {
            return [
                'ok' => false,
                'status' => 0,
                'error' => 'Unable to build VAPID JWT'
            ];
        }

        $publicKey = self::getVapidPublicKey();
        $ch = curl_init($endpoint);
        if ($ch === false) {
            return [
                'ok' => false,
                'status' => 0,
                'error' => 'Unable to initialize push request'
            ];
        }

        $headers = [
            'TTL: ' . self::PUSH_TTL_SECONDS,
            'Urgency: normal',
            'Authorization: vapid t=' . $jwt . ', k=' . $publicKey,
            'Content-Length: 0'
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POSTFIELDS => '',
        ]);

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($statusCode >= 200 && $statusCode < 300) {
            return [
                'ok' => true,
                'status' => $statusCode,
                'error' => null,
            ];
        }

        $error = trim((string)$curlError);
        if ($error === '') {
            $error = trim((string)$responseBody);
        }

        if (mb_strlen($error) > 1000) {
            $error = mb_substr($error, 0, 1000);
        }

        return [
            'ok' => false,
            'status' => $statusCode,
            'error' => $error !== '' ? $error : 'Push request failed',
        ];
    }

    private static function getAudienceFromEndpoint(string $endpoint): ?string {
        $parts = parse_url($endpoint);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower(trim((string)($parts['scheme'] ?? '')));
        $host = trim((string)($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (int)$parts['port'] : 0;

        if ($scheme === '' || $host === '') {
            return null;
        }

        $audience = $scheme . '://' . $host;
        if ($port > 0) {
            $isDefaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
            if (!$isDefaultPort) {
                $audience .= ':' . $port;
            }
        }

        return $audience;
    }

    private static function buildVapidJwt(string $audience): ?string {
        $privateKeyPem = self::getVapidPrivateKeyPem();
        if ($privateKeyPem === null) {
            return null;
        }

        $subject = trim((string)(getenv('PUSH_VAPID_SUBJECT') ?: ''));
        if ($subject === '') {
            $host = parse_url((string)APP_URL, PHP_URL_HOST) ?: 'localhost';
            $subject = 'mailto:admin@' . $host;
        }

        $header = ['typ' => 'JWT', 'alg' => 'ES256'];
        $claims = [
            'aud' => $audience,
            'exp' => time() + 12 * 60 * 60,
            'sub' => $subject,
        ];

        $headerPart = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $claimsPart = self::base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES));
        $signingInput = $headerPart . '.' . $claimsPart;

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            return null;
        }

        $derSignature = '';
        $signed = openssl_sign($signingInput, $derSignature, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($privateKey);
        if (!$signed) {
            return null;
        }

        $joseSignature = self::derToJoseSignature($derSignature, 64);
        if ($joseSignature === null) {
            return null;
        }

        return $signingInput . '.' . self::base64UrlEncode($joseSignature);
    }

    private static function getVapidPrivateKeyPem(): ?string {
        $raw = trim((string)(getenv('PUSH_VAPID_PRIVATE_KEY') ?: ''));
        if ($raw === '') {
            $raw = trim((string)(Setting::get('push_vapid_private_key') ?? ''));
        }
        if ($raw === '') {
            return null;
        }

        if (strpos($raw, 'BEGIN') !== false) {
            return $raw;
        }

        return null;
    }

    private static function derToJoseSignature(string $der, int $partLength): ?string {
        $offset = 0;
        if (ord($der[$offset]) !== 0x30) {
            return null;
        }
        $offset++;

        $seqLen = self::readAsn1Length($der, $offset);
        if ($seqLen === null) {
            return null;
        }

        if (ord($der[$offset]) !== 0x02) {
            return null;
        }
        $offset++;

        $rLen = self::readAsn1Length($der, $offset);
        if ($rLen === null) {
            return null;
        }
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;

        if (ord($der[$offset]) !== 0x02) {
            return null;
        }
        $offset++;

        $sLen = self::readAsn1Length($der, $offset);
        if ($sLen === null) {
            return null;
        }
        $s = substr($der, $offset, $sLen);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        $r = str_pad($r, $partLength / 2, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, $partLength / 2, "\x00", STR_PAD_LEFT);

        if (strlen($r) !== $partLength / 2 || strlen($s) !== $partLength / 2) {
            return null;
        }

        return $r . $s;
    }

    private static function readAsn1Length(string $der, int &$offset): ?int {
        if (!isset($der[$offset])) {
            return null;
        }

        $length = ord($der[$offset]);
        $offset++;

        if (($length & 0x80) === 0) {
            return $length;
        }

        $numBytes = $length & 0x7F;
        if ($numBytes < 1 || $numBytes > 4) {
            return null;
        }

        $length = 0;
        for ($i = 0; $i < $numBytes; $i++) {
            if (!isset($der[$offset])) {
                return null;
            }
            $length = ($length << 8) | ord($der[$offset]);
            $offset++;
        }

        return $length;
    }

    private static function base64UrlEncode(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

}