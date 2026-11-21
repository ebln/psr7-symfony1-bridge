<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests;

use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;
use ebln\Symfony1\Psr7Bridge\Tests\Support\SymfonyObjects;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testSymfonyObjectsWorkWithoutAProject(): void
    {
        $request = SymfonyObjects::request(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/a/b?x=1', 'HTTPS' => 'on', 'SERVER_PORT' => '443'],
            ['x' => '1']
        );
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://example.org/a/b?x=1', $request->getUri());
        self::assertSame(['x' => '1'], $request->getGetParameters());
        self::assertFalse($request->getContent());
        self::assertSame('{"a":1}', SymfonyObjects::request([], [], [], [], [], '{"a":1}')->getContent());

        $response = SymfonyObjects::response(['http_protocol' => 'HTTP/1.1']);
        $response->setStatusCode('404');
        self::assertSame('Not Found', $response->getStatusText());
        self::assertSame('HTTP/1.1', $response->getOptions()['http_protocol']);
        self::assertSame('HTTP/1.0', SymfonyObjects::response()->getOptions()['http_protocol']);
    }

    public function testGivenContentIsAvailableWhenTheRequestIsInitialized(): void
    {
        $request = SymfonyObjects::request(
            ['REQUEST_METHOD' => 'PUT', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            [],
            [],
            [],
            [],
            'a=b&c=d'
        );
        self::assertSame('a=b&c=d', $request->getContent());
        self::assertSame(['a' => 'b', 'c' => 'd'], $request->getPostParameters());
    }

    public function testBothImplementationsProvideEveryFactory(): void
    {
        foreach ([Factories::guzzle(), Factories::nyholm()] as $factories) {
            self::assertSame(200, $factories->response->createResponse()->getStatusCode());
            self::assertSame('/', $factories->serverRequest->createServerRequest('GET', '/')->getUri()->getPath());
            self::assertSame('x', (string) $factories->stream->createStream('x'));
            self::assertSame('example.org', $factories->uri->createUri('http://example.org/')->getHost());
        }
    }
}
