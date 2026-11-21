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

    /**
     * @return array<string, array{mixed}>
     */
    public function storedCodeThatIsNotScalarProvider(): array
    {
        return ['null' => [null], 'empty array' => [[]], 'array with a value' => [['a']], 'object' => [new \stdClass()]];
    }

    /**
     * @param mixed $stored a stored status code that is not a scalar
     *
     * @dataProvider storedCodeThatIsNotScalarProvider
     */
    public function testStatusThatIsNotScalarBecomesZeroAndIsRejected($stored): void
    {
        $response = SymfonyObjects::response();
        $this->replaceProperty($response, 'statusCode', $stored);

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
