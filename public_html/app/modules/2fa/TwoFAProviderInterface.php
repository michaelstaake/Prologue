<?php
interface TwoFAProviderInterface {
    public function getName(): string;
    public function getLabel(): string;
    public function isAvailable(int $userId): bool;
    public function sendChallenge(int $userId, string $ip): bool;
    public function verifyCode(int $userId, string $code, string $ip): bool;
    public function cleanup(int $userId): void;
}
