<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Internal;

/**
 * The characters that `setrawcookie()` refuses in the name, the value, the path and the domain of a cookie: comma,
 * semicolon, space, tab, CR, LF, vertical tab and form feed. PHP sends a samesite value unchanged. The bridge
 * checks all five fields, because written unchecked, these characters end the attribute and start another.
 *
 * @internal
 */
final class CookieSeparators
{
    private const PATTERN = '/[,; \t\r\n\x0B\x0C]/';

    public static function appearIn(string $text): bool
    {
        return 1 === preg_match(self::PATTERN, $text);
    }
}
