<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Integration;

use ebln\Symfony1\Psr7Bridge\RequestConverter;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;
use ebln\Symfony1\Psr7Bridge\Tests\Support\SymfonyObjects;
use Http\Psr7Test\ServerRequestIntegrationTest;
use Psr\Http\Message\ServerRequestInterface;

final class NyholmServerRequestTest extends ServerRequestIntegrationTest
{
    use BuildsWithFactories;

    /**
     * @var array<mixed, mixed>
     */
    protected $skippedTests = [
        'testUriPreserveHost_NoHost_Host' => 'A converted symfony request always has a host.',
    ];

    public function createSubject(): ServerRequestInterface
    {
        $f = $this->factories();

        return (new RequestConverter($f->serverRequest, $f->stream, $f->uploadedFile))->convert(SymfonyObjects::request());
    }

    protected function factories(): Factories
    {
        return Factories::nyholm();
    }
}
