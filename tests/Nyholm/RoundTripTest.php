<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Nyholm;

use ebln\Symfony1\Psr7Bridge\Tests\RoundTripTestCase;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;

final class RoundTripTest extends RoundTripTestCase
{
    /**
     * roundtrip/reason-phrase-default.
     */
    public function testReasonPhraseDefault(): void
    {
        $source = $this->factories()->response->createResponse(200, '');
        self::assertSame('', $source->getReasonPhrase());

        $back = $this->roundTrip($source);

        self::assertSame('', $back->getReasonPhrase());
    }

    protected function factories(): Factories
    {
        return Factories::nyholm();
    }
}
