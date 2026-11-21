<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Nyholm;

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
    public function testStatusOutsideTheRangeIsAccepted(int $code): void
    {
        $response = SymfonyObjects::response();
        @$response->setStatusCode($code);

        self::assertSame($code, $this->converter()->convert($response)->getStatusCode());
    }

    public function testStatusThatIsNotNumericBecomesZeroAndIsAccepted(): void
    {
        $response = SymfonyObjects::response();
        $response->setStatusCode('abc', 'Odd');

        $converted = $this->converter()->convert($response);

        self::assertSame(0, $converted->getStatusCode());
        self::assertSame('Odd', $converted->getReasonPhrase());
    }

    protected function factories(): Factories
    {
        return Factories::nyholm();
    }
}
