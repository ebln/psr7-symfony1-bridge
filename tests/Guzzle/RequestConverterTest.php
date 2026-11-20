<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Guzzle;

use ebln\Symfony1\Psr7Bridge\ConversionFailed;
use ebln\Symfony1\Psr7Bridge\Tests\RequestConverterTestCase;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;

final class RequestConverterTest extends RequestConverterTestCase
{
    protected function factories(): Factories
    {
        return Factories::guzzle();
    }

    public function testMalformedHostThrows(): void
    {
        try {
            $this->convert(['HTTP_HOST' => 'foo bar']);
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
        }
    }

    public function testValueThatIsNotAFileIsStoredWithADeprecation(): void
    {
        $deprecations = [];
        $previous = null;
        $previous = set_error_handler(static function (int $level, string $message, string $file, int $line) use (&$deprecations, &$previous): bool {
            if (\E_USER_DEPRECATED === $level) {
                $deprecations[] = $message;

                return true;
            }

            return null !== $previous && false !== $previous($level, $message, $file, $line);
        });
        try {
            $converted = $this->convert([], [], [], [], ['a' => ['b' => 'x']]);
        } finally {
            restore_error_handler();
        }

        self::assertSame(['a' => ['b' => 'x']], $converted->getUploadedFiles());
        self::assertCount(1, $deprecations);
        self::assertStringContainsString('withUploadedFiles()', $deprecations[0]);
    }
}
