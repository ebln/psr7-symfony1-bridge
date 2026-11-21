<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Nyholm;

use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;
use ebln\Symfony1\Psr7Bridge\Tests\UploadedFilesTestCase;

final class UploadedFilesTest extends UploadedFilesTestCase
{
    protected function factories(): Factories
    {
        return Factories::nyholm();
    }
}
