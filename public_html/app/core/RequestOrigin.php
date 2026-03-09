<?php

if (!function_exists('prologue_env_flag_enabled')) {
    function prologue_env_flag_enabled(string $name, bool $default = false): bool {
        $raw = getenv($name);
        if ($raw === false) {
            return $default;
        }

        $value = strtolower(trim((string)$raw));
        if ($value === '') {
            return $default;
        }

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('prologue_parse_proxy_allowlist')) {
    function prologue_parse_proxy_allowlist(): array {
        $raw = trim((string)(getenv('APP_TRUSTED_PROXIES') ?: ''));
        if ($raw === '') {
            return [];
        }

        $items = preg_split('/\s*,\s*/', $raw) ?: [];
        $allowlist = [];
        foreach ($items as $item) {
            $candidate = trim((string)$item);
            if ($candidate !== '') {
                $allowlist[] = $candidate;
            }
        }

        return $allowlist;
    }
}

if (!function_exists('prologue_ip_in_cidr')) {
    function prologue_ip_in_cidr(string $ip, string $cidr): bool {
        $cidr = trim($cidr);
        if ($cidr === '') {
            return false;
        }

        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }

        [$subnet, $prefixLengthRaw] = explode('/', $cidr, 2);
        $subnet = trim($subnet);
        $prefixLength = (int)trim($prefixLengthRaw);

        $ipPacked = @inet_pton($ip);
        $subnetPacked = @inet_pton($subnet);
        if ($ipPacked === false || $subnetPacked === false) {
            return false;
        }

        $size = strlen($ipPacked);
        if ($size !== strlen($subnetPacked)) {
            return false;
        }

        $maxPrefix = $size * 8;
        if ($prefixLength < 0 || $prefixLength > $maxPrefix) {
            return false;
        }

        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if ($fullBytes > 0 && substr($ipPacked, 0, $fullBytes) !== substr($subnetPacked, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        $ipByte = ord($ipPacked[$fullBytes]);
        $subnetByte = ord($subnetPacked[$fullBytes]);

        return (($ipByte & $mask) === ($subnetByte & $mask));
    }
}

if (!function_exists('prologue_request_is_from_trusted_proxy')) {
    function prologue_request_is_from_trusted_proxy(): bool {
        if (!prologue_env_flag_enabled('APP_TRUST_PROXY', false)) {
            return false;
        }

        $allowlist = prologue_parse_proxy_allowlist();
        if (empty($allowlist)) {
            return false;
        }

        $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remoteAddr === '') {
            return false;
        }

        foreach ($allowlist as $entry) {
            if (prologue_ip_in_cidr($remoteAddr, $entry)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('prologue_first_header_value')) {
    function prologue_first_header_value(string $header): string {
        $raw = trim((string)($_SERVER[$header] ?? ''));
        if ($raw === '') {
            return '';
        }

        $parts = explode(',', $raw);
        return trim((string)($parts[0] ?? ''));
    }
}

if (!function_exists('prologue_parse_forwarded_header')) {
    function prologue_parse_forwarded_header(): array {
        $raw = trim((string)($_SERVER['HTTP_FORWARDED'] ?? ''));
        if ($raw === '') {
            return ['proto' => '', 'host' => '', 'port' => 0];
        }

        $firstHop = trim((string)(explode(',', $raw)[0] ?? ''));
        if ($firstHop === '') {
            return ['proto' => '', 'host' => '', 'port' => 0];
        }

        $proto = '';
        $host = '';
        $port = 0;

        $pairs = explode(';', $firstHop);
        foreach ($pairs as $pair) {
            $segments = explode('=', $pair, 2);
            if (count($segments) !== 2) {
                continue;
            }

            $key = strtolower(trim((string)$segments[0]));
            $value = trim((string)$segments[1]);
            if ($value !== '' && $value[0] === '"' && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
            }

            if ($key === 'proto' && $proto === '') {
                $proto = $value;
            }
            if ($key === 'host' && $host === '') {
                $host = $value;
            }
        }

        if ($host !== '') {
            $hostParts = prologue_parse_authority($host);
            $host = $hostParts['host'];
            if ((int)$hostParts['port'] > 0) {
                $port = (int)$hostParts['port'];
            }
        }

        return ['proto' => $proto, 'host' => $host, 'port' => $port];
    }
}

if (!function_exists('prologue_normalize_scheme')) {
    function prologue_normalize_scheme(string $scheme): string {
        $candidate = strtolower(trim($scheme));
        if ($candidate === 'https') {
            return 'https';
        }
        if ($candidate === 'http') {
            return 'http';
        }
        return '';
    }
}

if (!function_exists('prologue_parse_authority')) {
    function prologue_parse_authority(string $authority): array {
        $authority = trim($authority);
        if ($authority === '') {
            return ['host' => '', 'port' => 0];
        }

        if (strpos($authority, '://') !== false) {
            $parsed = parse_url($authority);
            if (is_array($parsed)) {
                $host = trim((string)($parsed['host'] ?? ''));
                $port = (int)($parsed['port'] ?? 0);
                return [
                    'host' => prologue_sanitize_host($host),
                    'port' => prologue_sanitize_port($port),
                ];
            }
        }

        $host = $authority;
        $port = 0;

        if ($host !== '' && $host[0] === '[') {
            $endPos = strpos($host, ']');
            if ($endPos !== false) {
                $ipv6 = substr($host, 1, $endPos - 1);
                $remainder = substr($host, $endPos + 1);
                if (strpos($remainder, ':') === 0) {
                    $port = (int)substr($remainder, 1);
                }
                $host = $ipv6;
            }
        } else {
            $colonCount = substr_count($host, ':');
            if ($colonCount === 1) {
                $segments = explode(':', $host, 2);
                $host = trim((string)$segments[0]);
                $port = (int)trim((string)($segments[1] ?? '0'));
            }
        }

        return [
            'host' => prologue_sanitize_host($host),
            'port' => prologue_sanitize_port($port),
        ];
    }
}

if (!function_exists('prologue_sanitize_host')) {
    function prologue_sanitize_host(string $host): string {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }

        if (strpos($host, '/') !== false || strpos($host, ' ') !== false) {
            return '';
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        if ($host === 'localhost') {
            return $host;
        }

        if (!preg_match('/^[a-z0-9.-]+$/', $host)) {
            return '';
        }

        $labels = explode('.', $host);
        foreach ($labels as $label) {
            if ($label === '' || strlen($label) > 63) {
                return '';
            }
            if ($label[0] === '-' || substr($label, -1) === '-') {
                return '';
            }
        }

        return $host;
    }
}

if (!function_exists('prologue_sanitize_port')) {
    function prologue_sanitize_port(int $port): int {
        if ($port < 1 || $port > 65535) {
            return 0;
        }
        return $port;
    }
}

if (!function_exists('prologue_default_origin')) {
    function prologue_default_origin(): array {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            $https = true;
        }

        $scheme = $https ? 'https' : 'http';
        $authority = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost:8088'));
        $parsed = prologue_parse_authority($authority);

        $host = $parsed['host'] !== '' ? $parsed['host'] : 'localhost';
        $port = (int)$parsed['port'];

        if ($port === 0) {
            $serverPort = (int)($_SERVER['SERVER_PORT'] ?? 0);
            if ($serverPort > 0) {
                $port = $serverPort;
            }
        }

        return [
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
        ];
    }
}

if (!function_exists('prologue_resolve_request_origin')) {
    function prologue_resolve_request_origin(): array {
        $origin = prologue_default_origin();

        if (!prologue_request_is_from_trusted_proxy()) {
            return $origin;
        }

        $forwarded = prologue_parse_forwarded_header();

        $protoCandidate = prologue_first_header_value('HTTP_X_FORWARDED_PROTO');
        if ($protoCandidate === '') {
            $protoCandidate = (string)$forwarded['proto'];
        }
        $scheme = prologue_normalize_scheme($protoCandidate);
        if ($scheme !== '') {
            $origin['scheme'] = $scheme;
        }

        $hostCandidate = prologue_first_header_value('HTTP_X_FORWARDED_HOST');
        if ($hostCandidate === '') {
            $hostCandidate = (string)$forwarded['host'];
        }
        if ($hostCandidate !== '') {
            $parsedHost = prologue_parse_authority($hostCandidate);
            if ($parsedHost['host'] !== '') {
                $origin['host'] = $parsedHost['host'];
            }
            if ((int)$parsedHost['port'] > 0) {
                $origin['port'] = (int)$parsedHost['port'];
            }
        }

        $portCandidate = prologue_first_header_value('HTTP_X_FORWARDED_PORT');
        $port = prologue_sanitize_port((int)$portCandidate);
        if ($port === 0 && (int)$forwarded['port'] > 0) {
            $port = prologue_sanitize_port((int)$forwarded['port']);
        }
        if ($port > 0) {
            $origin['port'] = $port;
        }

        return $origin;
    }
}

if (!function_exists('prologue_is_https_request')) {
    function prologue_is_https_request(): bool {
        $origin = prologue_resolve_request_origin();
        return $origin['scheme'] === 'https';
    }
}

if (!function_exists('prologue_build_authority')) {
    function prologue_build_authority(string $host, int $port = 0): string {
        $authorityHost = $host;
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $authorityHost = '[' . $host . ']';
        }

        if ($port > 0) {
            return $authorityHost . ':' . $port;
        }

        return $authorityHost;
    }
}

if (!function_exists('prologue_origin_base_url')) {
    function prologue_origin_base_url(array $origin, string $basePath = ''): string {
        $scheme = ($origin['scheme'] ?? 'http') === 'https' ? 'https' : 'http';
        $host = prologue_sanitize_host((string)($origin['host'] ?? ''));
        if ($host === '') {
            $host = 'localhost';
        }

        $port = prologue_sanitize_port((int)($origin['port'] ?? 0));
        if (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) {
            $port = 0;
        }

        $authority = prologue_build_authority($host, $port);
        $prefix = trim($basePath, " \t\n\r\0\x0B/");
        $path = $prefix !== '' ? '/' . $prefix : '';

        return rtrim($scheme . '://' . $authority . $path, '/');
    }
}
