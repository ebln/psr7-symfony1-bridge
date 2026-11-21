<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Integration;

use ebln\Symfony1\Psr7Bridge\ResponseConverter;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;
use ebln\Symfony1\Psr7Bridge\Tests\Support\SymfonyObjects;
use Http\Psr7Test\ResponseIntegrationTest;
use Psr\Http\Message\ResponseInterface;

final class GuzzleResponseTest extends ResponseIntegrationTest
{
    use BuildsWithFactories;

    // Checked on 2026-11-20 with guzzlehttp/psr7 2.13.3 (composer.lock). Each test below also fails for a plain
    // guzzlehttp/psr7 object without the bridge. Check again when the locked version changes.
    // The suite can skip only a whole test method, so a skip also covers the data sets that guzzle passes.
    /**
     * @var array<mixed, mixed>
     */
    protected $skippedTests = [
        'testWithHeaderInvalidArguments' => 'guzzle accepts an empty array and false as a header value. A plain guzzlehttp/psr7 object fails this test as well.',
        'testWithAddedHeaderInvalidArguments' => 'guzzle accepts an empty array and false as a header value. A plain guzzlehttp/psr7 object fails this test as well.',
    ];

    public function createSubject(): ResponseInterface
    {
        $f = $this->factories();

        return (new ResponseConverter($f->response, $f->stream))->convert(SymfonyObjects::response(['http_protocol' => 'HTTP/1.1']));
    }

    protected function factories(): Factories
    {
        return Factories::guzzle();
    }
}
