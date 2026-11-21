<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Guzzle;

use ebln\Symfony1\Psr7Bridge\Tests\PublicResponseWriterTestCase;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;

final class PublicResponseWriterTest extends PublicResponseWriterTestCase
{
    protected function factories(): Factories
    {
        return Factories::guzzle();
    }
}
