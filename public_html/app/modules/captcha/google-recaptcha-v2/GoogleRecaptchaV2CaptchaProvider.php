<?php
class GoogleRecaptchaV2CaptchaProvider implements CaptchaProviderInterface {
    public function getName(): string {
        return 'google-recaptcha-v2';
    }

    public function getLabel(): string {
        return 'Google reCAPTCHA v2';
    }

    public function isAvailable(): bool {
        $siteKey = trim((string)(Setting::get('captcha_site_key') ?? ''));
        $secretKey = trim((string)(Setting::get('captcha_secret_key') ?? ''));
        return $siteKey !== '' && $secretKey !== '';
    }

    public function getScriptUrl(): string {
        return 'https://www.google.com/recaptcha/api.js';
    }

    public function getWidgetHtml(string $siteKey): string {
        $escaped = htmlspecialchars($siteKey, ENT_QUOTES, 'UTF-8');
        return '<div class="g-recaptcha" data-sitekey="' . $escaped . '" data-theme="dark"></div>';
    }

    public function getTokenFieldName(): string {
        return 'g-recaptcha-response';
    }

    public function verify(string $token, string $ip): bool {
        $secretKey = trim((string)(Setting::get('captcha_secret_key') ?? ''));
        if ($secretKey === '' || $token === '') {
            return false;
        }

        $postData = http_build_query([
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => $ip,
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 10,
            ],
        ]);

        $response = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
        if ($response === false) {
            return false;
        }

        $result = json_decode($response, true);
        return is_array($result) && !empty($result['success']);
    }

    public function getScriptDomains(): array {
        return ['https://www.google.com', 'https://www.gstatic.com'];
    }
}
