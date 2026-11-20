<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Integration;

use ebln\Symfony1\Psr7Bridge\RequestConverter;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;
use ebln\Symfony1\Psr7Bridge\Tests\Support\SymfonyObjects;
use Http\Psr7Test\ServerRequestIntegrationTest;
use Psr\Http\Message\ServerRequestInterface;

final class GuzzleServerRequestTest extends ServerRequestIntegrationTest
{
    use BuildsWithFactories;

    // Checked on 2026-11-20 with guzzlehttp/psr7 2.13.3 (composer.lock). Each test below also fails for a plain
    // guzzlehttp/psr7 object without the bridge. Check again when the locked version changes.
    // The suite can skip only a whole test method, so a skip also covers the data sets that guzzle passes.
    /**
     * @var array<mixed, mixed>
     */
    protected $skippedTests = [
        'testUriPreserveHost_NoHost_Host' => 'A converted symfony request always has a host.',
        'testWithHeaderInvalidArguments' => 'guzzle accepts an empty array and false as a header value. A plain guzzlehttp/psr7 object fails this test as well.',
        'testWithAddedHeaderInvalidArguments' => 'guzzle accepts an empty array and false as a header value. A plain guzzlehttp/psr7 object fails this test as well.',
        'testGetParsedBodyInvalid' => 'guzzle accepts a scalar as parsed body. A plain guzzlehttp/psr7 object fails this test as well.',
        'testMethodIsCaseSensitive' => 'guzzle returns the method in upper case. A plain guzzlehttp/psr7 object fails this test as well.',
        'testGetRequestTargetInOriginFormNormalizesUriWithMultipleLeadingSlashesInPath' => 'guzzle keeps the leading slashes in the request target. A plain guzzlehttp/psr7 object fails this test as well.',
    ];

    public function createSubject(): ServerRequestInterface
    {
        $f = $this->factories();

        return (new RequestConverter($f->serverRequest, $f->stream, $f->uploadedFile))->convert(SymfonyObjects::request());
    }

    protected function factories(): Factories
    {
        return Factories::guzzle();
    }
}
