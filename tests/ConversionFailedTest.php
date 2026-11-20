<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests;

use ebln\Symfony1\Psr7Bridge\BridgeException;
use ebln\Symfony1\Psr7Bridge\ConversionFailed;
use PHPUnit\Framework\TestCase;

final class ConversionFailedTest extends TestCase
{
    public function testCarriesReasonAndPrevious(): void
    {
        $previous = new \InvalidArgumentException('Invalid host');
        $failure = ConversionFailed::because('The symfony request cannot be converted', $previous);

        self::assertInstanceOf(BridgeException::class, $failure);
        self::assertInstanceOf(\RuntimeException::class, $failure);
        self::assertSame('The symfony request cannot be converted: Invalid host', $failure->getMessage());
        self::assertSame($previous, $failure->getPrevious());
        self::assertSame('No host', ConversionFailed::because('No host')->getMessage());
    }
}
