<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Internal;

use ebln\Symfony1\Psr7Bridge\Internal\CookieSeparators;
use PHPUnit\Framework\TestCase;

final class CookieSeparatorsTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public function separatorProvider(): array
    {
        return [
            'comma' => ['a,b'],
            'semicolon' => ['a;b'],
            'space' => ['a b'],
            'tab' => ["a\tb"],
            'carriage return' => ["a\rb"],
            'line feed' => ["a\nb"],
            'vertical tab' => ["a\x0Bb"],
            'form feed' => ["a\x0Cb"],
            'only a separator' => [' '],
        ];
    }

    /**
     * @dataProvider separatorProvider
     */
    public function testSeparatorIsFound(string $text): void
    {
        self::assertTrue(CookieSeparators::appearIn($text));
    }

    /**
     * @return array<string, array{string}>
     */
    public function plainProvider(): array
    {
        return [
            'empty' => [''],
            'equals sign' => ['a=b=c'],
            'percent encoding' => ['%20x'],
            'path' => ['/a/b'],
            'leading dot' => ['.example.org'],
            'quote and colon' => ['"a":b'],
        ];
    }

    /**
     * @dataProvider plainProvider
     */
    public function testPlainTextIsNotFound(string $text): void
    {
        self::assertFalse(CookieSeparators::appearIn($text));
    }
}
