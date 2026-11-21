<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Nyholm;

use ebln\Symfony1\Psr7Bridge\Tests\RequestConverterTestCase;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;

final class RequestConverterTest extends RequestConverterTestCase
{
    protected function factories(): Factories
    {
        return Factories::nyholm();
    }

    public function testMalformedHostIsAccepted(): void
    {
        self::assertSame('foo bar', $this->convert(['HTTP_HOST' => 'foo bar'])->getUri()->getHost());
    }

    public function testValueThatIsNotAFileIsStoredInTheRequest(): void
    {
        $converted = $this->convert([], [], [], [], ['a' => ['b' => 'x']]);

        self::assertSame(['a' => ['b' => 'x']], $converted->getUploadedFiles());
    }
}
