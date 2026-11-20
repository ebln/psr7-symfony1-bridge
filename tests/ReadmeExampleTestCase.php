<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests;

use ebln\Symfony1\Psr7Bridge\PublicResponseWriter;
use ebln\Symfony1\Psr7Bridge\RequestConverter;
use ebln\Symfony1\Psr7Bridge\ResponseConverter;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;
use ebln\Symfony1\Psr7Bridge\Tests\Support\SymfonyObjects;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Runs the usage example of the README. The factories of the test class replace the `HttpFactory` of the example.
 */
abstract class ReadmeExampleTestCase extends TestCase
{
    abstract protected function factories(): Factories;

    public function testUsageExample(): void
    {
        $sfRequest = SymfonyObjects::request(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/a?x=1']);
        $sfResponse = SymfonyObjects::response();
        $handler = new class {
            /** @var array<int, mixed> */
            public array $received = [];

            public function handle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
            {
                $this->received = [$request, $response];

                return $response->withStatus(201)->withHeader('X-Foo', 'bar');
            }
        };

        // The lines of the README example, from here.
        $factory = $this->factories();
        $requests = new RequestConverter($factory->serverRequest, $factory->stream, $factory->uploadedFile);
        $responses = new ResponseConverter($factory->response, $factory->stream);
        $writer = new PublicResponseWriter();

        $psrRequest = $requests->convert($sfRequest);
        $psrResponse = $responses->convert($sfResponse);

        $result = $handler->handle($psrRequest, $psrResponse);

        $writer->write($result, $sfResponse);
        // To here.

        self::assertSame([$psrRequest, $psrResponse], $handler->received);
        self::assertSame('POST', $psrRequest->getMethod());
        self::assertSame(201, $sfResponse->getStatusCode());
        self::assertSame('bar', $sfResponse->getHttpHeader('X-Foo'));
        self::assertSame(['X-Foo' => 'bar'], $sfResponse->getHttpHeaders());
    }
}
