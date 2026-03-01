<?php
require_once __DIR__ . '/TwoFAProviderInterface.php';

class TwoFAManager {
    private static ?array $providers = null;

    public static function getProviders(): array {
        if (self::$providers !== null) {
            return self::$providers;
        }

        self::$providers = [];
        $baseDir = __DIR__;
        $entries = @scandir($baseDir);
        if ($entries === false) {
            return self::$providers;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $dir = $baseDir . '/' . $entry;
            if (!is_dir($dir)) {
                continue;
            }

            $files = @scandir($dir);
            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                if (str_ends_with($file, 'TwoFAProvider.php')) {
                    $filePath = $dir . '/' . $file;
                    $className = basename($file, '.php');
                    require_once $filePath;
                    if (class_exists($className) && is_subclass_of($className, 'TwoFAProviderInterface')) {
                        self::$providers[] = new $className();
                    }
                    break;
                }
            }
        }

        return self::$providers;
    }

    public static function getAvailableProvider(int $userId): ?TwoFAProviderInterface {
        foreach (self::getProviders() as $provider) {
            if ($provider->isAvailable($userId)) {
                return $provider;
            }
        }
        return null;
    }

    public static function getProviderByName(string $name): ?TwoFAProviderInterface {
        foreach (self::getProviders() as $provider) {
            if ($provider->getName() === $name) {
                return $provider;
            }
        }
        return null;
    }
}
