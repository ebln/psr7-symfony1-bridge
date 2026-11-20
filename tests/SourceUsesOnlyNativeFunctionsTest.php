<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Dev packages install symfony/polyfill-php80, so a PHP 8 function such as str_contains() in src/
 * would pass every test on PHP 7.4 and fail for a user without the polyfill.
 */
final class SourceUsesOnlyNativeFunctionsTest extends TestCase
{
    /**
     * @requires PHP < 8.0
     */
    public function testSourceCallsOnlyNativeFunctions(): void
    {
        $violations = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/../src', \FilesystemIterator::SKIP_DOTS));
        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            foreach (self::calledFunctions($source) as $name) {
                if (!function_exists($name) || !(new \ReflectionFunction($name))->isInternal()) {
                    $violations[] = $file->getFilename().': '.$name;
                }
            }
        }
        sort($violations);

        self::assertSame([], $violations, 'src/ calls functions that are not part of PHP 7.4 itself');
    }

    /**
     * @return list<string>
     */
    private static function calledFunctions(string $source): array
    {
        $tokens = token_get_all($source);
        $names = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || T_STRING !== $token[0] || '(' !== self::nextSignificant($tokens, $index)) {
                continue;
            }
            $name = $token[1];
            $start = $index - 1;
            // Walk back over the namespace parts of the name.
            while ($start >= 0 && is_array($tokens[$start]) && in_array($tokens[$start][0], [T_STRING, T_NS_SEPARATOR], true)) {
                $name = $tokens[$start][1].$name;
                --$start;
            }
            while ($start >= 0 && is_array($tokens[$start]) && in_array($tokens[$start][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                --$start;
            }
            $before = $start >= 0 && is_array($tokens[$start]) ? $tokens[$start][0] : null;
            if (in_array($before, [T_NEW, T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                continue;
            }
            if (false !== strpos(ltrim($name, '\\'), '\\')) {
                continue;
            }
            $names[] = ltrim($name, '\\');
        }

        return $names;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     *
     * @return string|null
     */
    private static function nextSignificant(array $tokens, int $index)
    {
        for ($next = $index + 1; isset($tokens[$next]); ++$next) {
            $token = $tokens[$next];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) ? $token[1] : $token;
        }

        return null;
    }
}
