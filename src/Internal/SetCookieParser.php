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
        $yearTokens = self::findYearTokens($expires);
        $year = null === $yearTokens ? null : self::readYear($expires);
        if (null === $yearTokens || null === $year) {
            return null;
        }
        $matches = false;
        foreach ($yearTokens as $yearToken) {
            $matches = $matches || self::expectedYear($yearToken) === $year;
        }
        if (!$matches) {
            return null;
        }
        // A date without a zone is read as UTC, not in the default time zone of the process.
        $date = date_create($expires, new \DateTimeZone('UTC'));
        if (false === $date) {
            return null;
        }

        return max(1, $date->getTimestamp());
    }

    /**
     * The year that PHP must have read for a year token of two or four digits, with the rule of PHP for two digits.
     */
    private static function expectedYear(string $yearToken): int
    {
        $year = (int) $yearToken;
        if (4 === \strlen($yearToken)) {
            return $year;
        }

        return $year < 70 ? 2000 + $year : 1900 + $year;
    }

    /**
     * Check 1, on tokens. RFC 6265 5.1.1: a date has a time (h:m:s), a day of month, a month name and a year of two
     * or four digits. Returns every token that can be the year, or null when the time, the day, the month or all
     * year tokens are missing. date_create() also accepts texts such as `now` or `+1 day`, which it resolves with the
     * system clock; this check rejects them. The digits of a numeric zone such as `+0100` also look like a year, and
     * a zone can stand before or after the year. So parseDate() accepts the value when the year that PHP read
     * equals the year of at least one of these tokens.
     *
     * @return list<string>|null
     */
    private static function findYearTokens(string $expires): ?array
    {
        $tokens = preg_split('/[^0-9A-Za-z:]+/', $expires, -1, PREG_SPLIT_NO_EMPTY);
        if (false === $tokens) {
            return null;
        }

        $time = false;
        $day = false;
        $month = false;
        $years = [];
        foreach ($tokens as $token) {
            if (!$time && 1 === preg_match('/^\d{1,2}:\d{1,2}:\d{1,2}(?!\d)/', $token)) {
                $time = true;
            } elseif (!$day && 1 === preg_match('/^\d{1,2}(?!\d)/', $token)) {
                $day = true;
            } elseif (!$month && 1 === preg_match('/^(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)/i', $token)) {
                $month = true;
            } elseif (1 === preg_match('/^(\d{4}|\d{2})(?!\d)/', $token, $found)) {
                $years[] = $found[1];
            }
        }

        return $time && $day && $month && [] !== $years ? $years : null;
    }

    /**
     * Check 2, on the result of date_parse(). Returns the year, or null when date_parse() reports an error or when
     * one of year, month, day, hour, minute or second is not set. A date without a year, such as
     * `Dec 25 10:00:00 +0100`, would otherwise take its year from the system clock in date_create().
     */
    private static function readYear(string $expires): ?int
    {
        $parts = date_parse($expires);
        if (
            0 !== $parts['error_count']
            || false === $parts['year']
            || false === $parts['month']
            || false === $parts['day']
            || false === $parts['hour']
            || false === $parts['minute']
            || false === $parts['second']
        ) {
            return null;
        }

        return $parts['year'];
    }
}
