<?php
interface CaptchaProviderInterface {
    public function getName(): string;
    public function getLabel(): string;
    public function isAvailable(): bool;
    public function getScriptUrl(): string;
    public function getWidgetHtml(string $siteKey): string;
    public function getTokenFieldName(): string;
    public function verify(string $token, string $ip): bool;
    public function getScriptDomains(): array;
}
