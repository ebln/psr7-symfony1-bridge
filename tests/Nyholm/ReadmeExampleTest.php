<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Nyholm;

use ebln\Symfony1\Psr7Bridge\Tests\ReadmeExampleTestCase;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;

final class ReadmeExampleTest extends ReadmeExampleTestCase
{
    protected function factories(): Factories
    {
        return Factories::nyholm();
    }
}
