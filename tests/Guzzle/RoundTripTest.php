<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Guzzle;

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
        self::assertSame('OK', $source->getReasonPhrase());

        $back = $this->roundTrip($source);

        self::assertSame('OK', $back->getReasonPhrase());
    }

    /**
     * roundtrip/reason-phrase-default: nyholm keeps an empty phrase empty, guzzle gives the result its default phrase.
     */
    public function testEmptyReasonPhraseOfANyholmSourceBecomesTheDefaultPhrase(): void
    {
        $source = Factories::nyholm()->response->createResponse(200, '');
        self::assertSame('', $source->getReasonPhrase());

        $back = $this->roundTrip($source);

        self::assertSame('OK', $back->getReasonPhrase());
    }

    protected function factories(): Factories
    {
        return Factories::guzzle();
    }
}
