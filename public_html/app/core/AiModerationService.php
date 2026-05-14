<?php
class AiModerationService {
    public const SETTING_BASE_URL = 'ai_base_url';
    public const SETTING_MODEL = 'ai_model';
    public const SETTING_API_KEY = 'ai_api_key';
    public const SETTING_SCAN_ENABLED = 'ai_scan_enabled';
    public const SETTING_LAST_TEST_STATUS = 'ai_last_test_status';
    public const SETTING_LAST_TESTED_AT = 'ai_last_tested_at';
    public const SETTING_LAST_TEST_ERROR = 'ai_last_test_error';
    public const SETTING_VERIFIED_SIGNATURE = 'ai_last_verified_signature';

    private const TEST_TIMEOUT_SECONDS = 10;
    private const MODERATION_TIMEOUT_SECONDS = 4;
    private const ALLOWED_CATEGORIES = ['rude', 'phishing', 'adult'];

    public static function normalizeBaseUrl(string $value): string {
        return rtrim(trim($value), "/ \t\n\r\0\x0B");
    }

    public static function normalizeModel(string $value): string {
        return trim($value);
    }

    public static function getConfiguredBaseUrl(): string {
        return self::normalizeBaseUrl((string)(Setting::get(self::SETTING_BASE_URL) ?? ''));
    }

    public static function getConfiguredModel(): string {
        return self::normalizeModel((string)(Setting::get(self::SETTING_MODEL) ?? ''));
    }

    public static function getConfiguredApiKey(): string {
        return trim((string)(Setting::get(self::SETTING_API_KEY) ?? ''));
    }

    public static function hasConfiguredApiKey(): bool {
        return self::getConfiguredApiKey() !== '';
    }

    public static function getMaskedApiKey(): string {
        $apiKey = self::getConfiguredApiKey();
        if ($apiKey === '') {
            return '';
        }

        $length = function_exists('mb_strlen') ? (int)mb_strlen($apiKey, 'UTF-8') : (int)strlen($apiKey);
        if ($length <= 8) {
            return str_repeat('*', max(4, $length));
        }

        $prefix = function_exists('mb_substr') ? mb_substr($apiKey, 0, 4, 'UTF-8') : substr($apiKey, 0, 4);
        $suffix = function_exists('mb_substr') ? mb_substr($apiKey, -4, null, 'UTF-8') : substr($apiKey, -4);
        return $prefix . str_repeat('*', max(4, $length - 8)) . $suffix;
    }

    public static function buildConfigSignature(string $baseUrl, string $model, string $apiKey): string {
        $normalizedBaseUrl = self::normalizeBaseUrl($baseUrl);
        $normalizedModel = self::normalizeModel($model);
        $normalizedApiKey = trim($apiKey);

        if ($normalizedBaseUrl === '' || $normalizedModel === '' || $normalizedApiKey === '') {
            return '';
        }

        return hash('sha256', $normalizedBaseUrl . "\n" . $normalizedModel . "\n" . $normalizedApiKey);
    }

    public static function getCurrentConfigSignature(): string {
        return self::buildConfigSignature(
            self::getConfiguredBaseUrl(),
            self::getConfiguredModel(),
            self::getConfiguredApiKey()
        );
    }

    public static function isVerifiedForCurrentConfig(): bool {
        $signature = self::getCurrentConfigSignature();
        if ($signature === '') {
            return false;
        }

        $stored = trim((string)(Setting::get(self::SETTING_VERIFIED_SIGNATURE) ?? ''));
        return $stored !== '' && hash_equals($stored, $signature);
    }

    public static function isScanEnabled(): bool {
        return (string)(Setting::get(self::SETTING_SCAN_ENABLED) ?? '0') === '1'
            && self::isVerifiedForCurrentConfig();
    }

    public static function clearVerificationState(): void {
        Setting::set(self::SETTING_VERIFIED_SIGNATURE, '');
        Setting::set(self::SETTING_LAST_TEST_STATUS, 'pending');
        Setting::set(self::SETTING_LAST_TESTED_AT, '');
        Setting::set(self::SETTING_LAST_TEST_ERROR, '');
    }

    public static function markVerified(string $baseUrl, string $model, string $apiKey): void {
        Setting::set(self::SETTING_VERIFIED_SIGNATURE, self::buildConfigSignature($baseUrl, $model, $apiKey));
        Setting::set(self::SETTING_LAST_TEST_STATUS, 'verified');
        Setting::set(self::SETTING_LAST_TESTED_AT, gmdate('Y-m-d H:i:s'));
        Setting::set(self::SETTING_LAST_TEST_ERROR, '');
    }

    public static function markFailed(string $error): void {
        Setting::set(self::SETTING_VERIFIED_SIGNATURE, '');
        Setting::set(self::SETTING_LAST_TEST_STATUS, 'failed');
        Setting::set(self::SETTING_LAST_TESTED_AT, gmdate('Y-m-d H:i:s'));
        Setting::set(self::SETTING_LAST_TEST_ERROR, trim($error));
    }

    public static function testConnectionWithConfig(string $baseUrl, string $model, string $apiKey): array {
        $content = self::requestChatCompletion(
            $baseUrl,
            $model,
            $apiKey,
            [
                ['role' => 'system', 'content' => 'Respond with OK only.'],
                ['role' => 'user', 'content' => 'This is a test. Respond with OK if it\'s working'],
            ],
            self::TEST_TIMEOUT_SECONDS,
            8
        );

        $normalized = strtoupper(trim($content));
        if ($normalized === 'OK' || str_starts_with($normalized, 'OK')) {
            return ['success' => true, 'message' => 'Connection verified.'];
        }

        throw new RuntimeException('The AI endpoint responded, but it did not confirm the test with OK.');
    }

    public static function moderateContent(string $content, string $contentType = 'message'): array {
        $rawContent = trim($content);
        if ($rawContent === '') {
            return ['status' => 'clean', 'flagged' => false, 'categories' => [], 'reason' => ''];
        }

        $responseText = self::requestChatCompletion(
            self::getConfiguredBaseUrl(),
            self::getConfiguredModel(),
            self::getConfiguredApiKey(),
            [
                [
                    'role' => 'system',
                    'content' => 'You are a content safety classifier for Prologue. Evaluate only the provided content. Categories: rude, phishing, adult. Respond with JSON only using this exact shape: {"flagged":true|false,"categories":["rude"|"phishing"|"adult"],"reason":"short reason"}. Set flagged to true if any listed category applies. Use only the allowed categories. Keep reason under 160 characters.'
                ],
                [
                    'role' => 'user',
                    'content' => "Content type: " . $contentType . "\nContent:\n" . $rawContent
                ],
            ],
            self::MODERATION_TIMEOUT_SECONDS,
            120
        );

        $decoded = self::extractJsonObject($responseText);
        if ($decoded === null) {
            throw new RuntimeException('The AI moderation response was not valid JSON.');
        }

        $categories = [];
        $rawCategories = $decoded['categories'] ?? [];
        if (is_array($rawCategories)) {
            foreach ($rawCategories as $category) {
                $normalizedCategory = strtolower(trim((string)$category));
                if (in_array($normalizedCategory, self::ALLOWED_CATEGORIES, true) && !in_array($normalizedCategory, $categories, true)) {
                    $categories[] = $normalizedCategory;
                }
            }
        }

        $flagged = !empty($decoded['flagged']) || !empty($categories);
        $reason = trim((string)($decoded['reason'] ?? ''));

        return [
            'status' => $flagged ? 'flagged' : 'clean',
            'flagged' => $flagged,
            'categories' => $categories,
            'reason' => $reason,
        ];
    }

    public static function buildAutomatedReportReason(string $contentType, array $moderationResult): string {
        $normalizedContentType = trim($contentType) !== '' ? trim($contentType) : 'content';
        $categories = [];
        foreach (($moderationResult['categories'] ?? []) as $category) {
            $normalizedCategory = strtolower(trim((string)$category));
            if (in_array($normalizedCategory, self::ALLOWED_CATEGORIES, true) && !in_array($normalizedCategory, $categories, true)) {
                $categories[] = $normalizedCategory;
            }
        }

        $reason = 'AI moderation flagged this ' . $normalizedContentType;
        if (!empty($categories)) {
            $reason .= ' for: ' . implode(', ', $categories);
        }

        $summary = trim((string)($moderationResult['reason'] ?? ''));
        if ($summary !== '') {
            $reason .= '. ' . $summary;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($reason, 0, 1000, 'UTF-8');
        }

        return substr($reason, 0, 1000);
    }

    private static function requestChatCompletion(string $baseUrl, string $model, string $apiKey, array $messages, int $timeoutSeconds, int $maxTokens): string {
        $normalizedBaseUrl = self::normalizeBaseUrl($baseUrl);
        $normalizedModel = self::normalizeModel($model);
        $normalizedApiKey = trim($apiKey);

        if ($normalizedBaseUrl === '' || $normalizedModel === '' || $normalizedApiKey === '') {
            throw new RuntimeException('Base URL, model, and API key are required.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL is required for AI requests.');
        }

        $payload = json_encode([
            'model' => $normalizedModel,
            'messages' => $messages,
            'temperature' => 0,
            'max_tokens' => $maxTokens,
        ]);

        if (!is_string($payload) || $payload === '') {
            throw new RuntimeException('Unable to encode the AI request payload.');
        }

        $ch = curl_init(self::buildEndpointUrl($normalizedBaseUrl));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $normalizedApiKey,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_CONNECTTIMEOUT => min(5, max(1, $timeoutSeconds)),
        ]);

        $response = curl_exec($ch);
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);

        if ($response === false) {
            $message = trim($curlError);
            throw new RuntimeException($message !== '' ? $message : 'The AI request failed.');
        }

        $decoded = json_decode($response, true);
        if ($httpStatus < 200 || $httpStatus >= 300) {
            $errorMessage = '';
            if (is_array($decoded)) {
                $errorMessage = trim((string)($decoded['error']['message'] ?? $decoded['message'] ?? ''));
            }
            if ($errorMessage === '') {
                $errorMessage = 'AI request failed with HTTP ' . $httpStatus . '.';
            }
            throw new RuntimeException($errorMessage);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('The AI endpoint returned invalid JSON.');
        }

        $content = self::extractAssistantContent($decoded);
        if ($content === '') {
            throw new RuntimeException('The AI endpoint returned an empty response.');
        }

        return $content;
    }

    private static function buildEndpointUrl(string $baseUrl): string {
        $normalized = self::normalizeBaseUrl($baseUrl);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('#/chat/completions$#i', $normalized) === 1) {
            return $normalized;
        }

        return $normalized . '/chat/completions';
    }

    private static function extractAssistantContent(array $decoded): string {
        $choice = $decoded['choices'][0] ?? null;
        if (!is_array($choice)) {
            return '';
        }

        $message = $choice['message']['content'] ?? $choice['text'] ?? '';
        if (is_string($message)) {
            return trim($message);
        }

        if (!is_array($message)) {
            return '';
        }

        $parts = [];
        foreach ($message as $item) {
            if (is_string($item)) {
                $parts[] = $item;
                continue;
            }
            if (is_array($item)) {
                $text = $item['text'] ?? $item['content'] ?? '';
                if (is_string($text) && $text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    private static function extractJsonObject(string $content): ?array {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($trimmed, $start, $end - $start + 1), true);
        return is_array($decoded) ? $decoded : null;
    }
}