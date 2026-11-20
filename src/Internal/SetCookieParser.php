<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Internal;

use ebln\Symfony1\Psr7Bridge\ConversionFailed;

/**
 * Parses the value of one `Set-Cookie` header. The cookie value is not decoded.
 *
 * @internal
 */
final class SetCookieParser
{
    /**
     * 9999-12-31 23:59:59 UTC. setrawcookie() rejects a later expiry time: PHP 8 throws a ValueError,
     * PHP 7.4 emits a warning and returns false. No expiry time leaves the parser above this value.
     */
    private const LATEST_EXPIRE = 253402300799;

    /**
     * @param callable(): int $now returns the current Unix timestamp; called only for a valid `Max-Age`
     *
     * @throws ConversionFailed when the header value has no cookie name
     */
    public static function parse(string $header, callable $now): SetCookie
    {
        $parts = explode(';', $header);
        [$name, $value] = self::split(array_shift($parts));
        if ('' === $name) {
            throw ConversionFailed::because('A Set-Cookie value has no cookie name');
        }

        $attributes = [];
        $maxAge = null;
        $expires = null;
        foreach ($parts as $part) {
            [$attributeName, $attributeValue] = self::split($part);
            $attributeName = strtolower($attributeName);
            if ('max-age' === $attributeName) {
                // RFC 6265 5.2.2: an invalid value is ignored, an earlier valid value stays.
                if (1 === preg_match('/^-?\d+$/D', $attributeValue)) {
                    $maxAge = $attributeValue;
                }
            } elseif ('expires' === $attributeName) {
                // RFC 6265 5.2.1: the same for a date that cannot be parsed.
                $date = self::parseDate($attributeValue);
                if (null !== $date) {
                    $expires = $date;
                }
            } elseif ('' !== $attributeName) {
                $attributes[$attributeName] = $attributeValue;
            }
        }

        return new SetCookie(
            $name,
            $value,
            self::expire($maxAge, $expires, $now),
            '' === ($attributes['path'] ?? '') ? null : $attributes['path'],
            $attributes['domain'] ?? '',
            isset($attributes['secure']),
            isset($attributes['httponly']),
            $attributes['samesite'] ?? ''
        );
    }

    /**
     * @return array{string, string} the trimmed text before and after the first `=`
     */
    private static function split(string $part): array
    {
        $position = strpos($part, '=');
        if (false === $position) {
            return [trim($part), ''];
        }

        return [trim(substr($part, 0, $position)), trim(substr($part, $position + 1))];
    }

    /**
     * @param callable(): int $now
     */
    private static function expire(?string $maxAge, ?int $expires, callable $now): ?int
    {
        if (null === $maxAge) {
            return null === $expires ? null : min($expires, self::LATEST_EXPIRE);
        }
        if ('-' === $maxAge[0] || 0 === (int) $maxAge) {
            return 1;
        }
        $current = $now();
        $seconds = (int) $maxAge;

        // The comparison avoids an integer overflow, which would give a float.
        return $seconds > self::LATEST_EXPIRE - $current ? self::LATEST_EXPIRE : $current + $seconds;
    }

    private static function parseDate(string $expires): ?int
    {
        if ('' === $expires) {
            return null;
        }
        // A date without a zone is read as UTC, not in the default time zone of the process.
        $date = date_create($expires, new \DateTimeZone('UTC'));
        if (false === $date) {
            return null;
        }

        return max(1, $date->getTimestamp());
    }
}
