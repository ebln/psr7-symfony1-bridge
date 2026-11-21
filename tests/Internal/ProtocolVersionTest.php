<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Internal;

use ebln\Symfony1\Psr7Bridge\Internal\ProtocolVersion;
use PHPUnit\Framework\TestCase;

final class ProtocolVersionTest extends TestCase
{
    /**
     * @param mixed $value a SERVER_PROTOCOL value of any type
     *
     * @dataProvider provideValues
     */
    public function testParses($value, string $expected): void
    {
        self::assertSame($expected, ProtocolVersion::fromServerProtocol($value));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function provideValues(): array
    {
        return [
            'request/protocol-version-parsing 1.0' => ['HTTP/1.0', '1.0'],
            'HTTP/1.1' => ['HTTP/1.1', '1.1'],
            'HTTP/2.0' => ['HTTP/2.0', '2.0'],
            'HTTP/2' => ['HTTP/2', '2'],
            'no version' => ['HTTP/', '1.1'],
            'null' => [null, '1.1'],
            'garbage' => ['garbage', '1.1'],
            'integer' => [123, '1.1'],
            'text before HTTP' => ['xHTTP/1.0', '1.1'],
            'text after the version' => ['HTTP/1.0x', '1.1'],
        ];
    }
}
