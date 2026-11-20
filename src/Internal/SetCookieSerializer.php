<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Internal;

use ebln\Symfony1\Psr7Bridge\ConversionFailed;

/**
 * Writes one cookie of `sfWebResponse::getCookies()` as the value of a `Set-Cookie` header.
 * It writes the text that PHP would send for what symfony stores. It does not change a value, and it throws for a
 * value that would split the header.
 *
 * @internal
 */
final class SetCookieSerializer
{
    /**
     * The characters that `setrawcookie()` refuses in a name, a value and a path: comma, semicolon, space, tab,
     * CR, LF, vertical tab and form feed. Written unchecked, they would end the attribute and start another.
     */
    private const SEPARATORS = '/[,; \t\r\n\x0B\x0C]/';

    /**
     * @param array<mixed> $cookie keys `name`, `value`, `expire`, `path`, `domain`, `secure`, `httpOnly`, `samesite`;
     *                             a missing key and `null` count as "not set"; a value that is neither scalar nor
     *                             stringable counts as "not set" too
     *
     * @throws ConversionFailed when the cookie has no name, or when the name, the value, the path, the domain
     *                          or the samesite holds a character that would end or split the header attribute
     */
    public static function serialize(array $cookie): string
    {
        $name = self::text($cookie['name'] ?? null);
        if ('' === $name) {
            throw ConversionFailed::because('A cookie has no name');
        }
        if (1 === preg_match(self::SEPARATORS, $name) || false !== strpos($name, '=')) {
            throw ConversionFailed::because('A cookie cannot be written as Set-Cookie: its name holds a separator character');
        }

        $parts = [$name.'='.self::checkedText($cookie, 'value', $name)];

        $expire = $cookie['expire'] ?? null;
        if (is_numeric($expire) && 0 !== (int) $expire) {
            $parts[] = 'Expires='.gmdate('D, d M Y H:i:s \G\M\T', (int) $expire);
        }
        foreach (['Path' => 'path', 'Domain' => 'domain'] as $attribute => $key) {
            $text = self::checkedText($cookie, $key, $name);
            if ('' !== $text) {
                $parts[] = $attribute.'='.$text;
            }
        }
        if (self::flag($cookie['secure'] ?? null)) {
            $parts[] = 'Secure';
        }
        if (self::flag($cookie['httpOnly'] ?? null)) {
            $parts[] = 'HttpOnly';
        }
        $sameSite = self::checkedText($cookie, 'samesite', $name);
        if ('' !== $sameSite) {
            $parts[] = 'SameSite='.$sameSite;
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<mixed> $cookie
     *
     * @throws ConversionFailed when the text holds a separator character
     */
    private static function checkedText(array $cookie, string $field, string $name): string
    {
        $text = self::text($cookie[$field] ?? null);
        if (1 === preg_match(self::SEPARATORS, $text)) {
            throw ConversionFailed::because(sprintf('The cookie "%s" cannot be written as Set-Cookie: its %s holds a separator character', $name, $field));
        }

        return $text;
    }

    /**
     * @param mixed $value a value of a symfony cookie array; a scalar and an object with `__toString()` give their
     *                     text, as PHP coerces them for `setrawcookie()`, anything else gives ''
     */
    private static function text($value): string
    {
        if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @param mixed $value a value of a symfony cookie array; symfony stores `httpOnly` as it was passed, and PHP
     *                     reads the flags of `setrawcookie()` by truthiness
     */
    private static function flag($value): bool
    {
        if (is_array($value)) {
            return [] !== $value;
        }

        // PHP reads the option by truthiness; an object is true.
        return is_object($value) || (is_scalar($value) && (bool) $value);
    }
}
