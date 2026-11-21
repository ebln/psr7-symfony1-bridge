<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Integration;

use ebln\Symfony1\Psr7Bridge\ResponseConverter;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;
use ebln\Symfony1\Psr7Bridge\Tests\Support\SymfonyObjects;
use Http\Psr7Test\ResponseIntegrationTest;
use Psr\Http\Message\ResponseInterface;

final class NyholmResponseTest extends ResponseIntegrationTest
{
    use BuildsWithFactories;

    public function createSubject(): ResponseInterface
    {
        $f = $this->factories();

        return (new ResponseConverter($f->response, $f->stream))->convert(SymfonyObjects::response(['http_protocol' => 'HTTP/1.1']));
    }

    protected function factories(): Factories
    {
        return Factories::nyholm();
    }
}
