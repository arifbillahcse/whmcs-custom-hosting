<?php

declare(strict_types=1);

namespace WHMCS\Module\Server\ThirdPartyServices;

/**
 * Validation / normalisation of admin input. Every method returns a clean
 * value, or null when the input is present but invalid.
 */
final class Input
{
    /**
     * Absolute http(s) URL without trailing slash. '' stays ''.
     */
    public static function url(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false || mb_strlen($value) > 255) {
            return null;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? rtrim($value, '/') : null;
    }

    /**
     * Y-m-d date. '' becomes '' (meaning "no date").
     */
    public static function date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return ($parsed && $parsed->format('Y-m-d') === $value) ? $value : null;
    }

    /**
     * IPv4 or IPv6 address. '' stays ''.
     */
    public static function ip(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : null;
    }

    /**
     * Hostnames, one per line (max 10). Null if any line is not a hostname.
     *
     * @return string[]|null
     */
    public static function nameservers(string $value): ?array
    {
        $lines = array_map('strtolower', self::lines($value));
        if (count($lines) > 10) {
            return null;
        }

        foreach ($lines as $line) {
            if (filter_var($line, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
                || !str_contains($line, '.')
            ) {
                return null;
            }
        }

        return array_values(array_unique($lines));
    }

    /**
     * Single-line text, control characters removed, length-limited.
     */
    public static function text(string $value, int $maxLength): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';

        return mb_substr(trim($value), 0, $maxLength);
    }

    /**
     * Multi-line text: keeps newlines/tabs, strips other control characters.
     */
    public static function multiline(string $value, int $maxLength = 5000): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return mb_substr(trim($value), 0, $maxLength);
    }

    /**
     * @return string[] non-empty trimmed lines
     */
    public static function lines(string $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn (string $l): bool => $l !== ''));
    }
}
