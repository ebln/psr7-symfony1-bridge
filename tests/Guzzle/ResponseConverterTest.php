<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Guzzle;

use ebln\Symfony1\Psr7Bridge\ConversionFailed;
use ebln\Symfony1\Psr7Bridge\Tests\ResponseConverterTestCase;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;
use ebln\Symfony1\Psr7Bridge\Tests\Support\SymfonyObjects;

final class ResponseConverterTest extends ResponseConverterTestCase
{
    /**
     * @return array<string, array{int}>
     */
    public function outsideTheRangeProvider(): array
    {
        return ['above' => [600], 'below' => [99]];
    }

    /**
     * @dataProvider outsideTheRangeProvider
     */
    public function testStatusOutsideTheRangeFails(int $code): void
    {
        $response = SymfonyObjects::response();
        @$response->setStatusCode($code);

        try {
            $this->converter()->convert($response);
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
        }
    }

    public function testStatusThatIsNotNumericBecomesZeroAndIsRejected(): void
    {
        $response = SymfonyObjects::response();
        $response->setStatusCode('abc', 'Odd');

        try {
            $this->converter()->convert($response);
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
        }
    }

    protected function factories(): Factories
    {
        return Factories::guzzle();
    }
}
