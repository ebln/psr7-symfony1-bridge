<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests;

use ebln\Symfony1\Psr7Bridge\PublicResponseWriter;
use ebln\Symfony1\Psr7Bridge\ResponseConverter;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;
use ebln\Symfony1\Psr7Bridge\Tests\Support\ResponseSnapshot;
use ebln\Symfony1\Psr7Bridge\Tests\Support\SymfonyObjects;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Writes a PSR-7 response into an sfWebResponse and converts it back. Every difference of the
 * design's exception list for round trips has a test that carries the identifier of its row.
 */
abstract class RoundTripTestCase extends TestCase
{
    abstract protected function factories(): Factories;

    public function testEverythingElseIsEqual(): void
    {
        $source = $this->factories()->response->createResponse(404, 'Not Found')
            ->withHeader('X-Foo', 'bar')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader(
                'Set-Cookie',
                'sid=abc; Expires=Wed, 21 Oct 2015 07:28:00 GMT; Path=/app; Domain=example.org; Secure; HttpOnly; SameSite=Strict'
            )
            ->withBody($this->factories()->stream->createStream('payload'));

        $back = $this->roundTrip($source);

        self::assertSame(ResponseSnapshot::of($source), ResponseSnapshot::of($back));
    }

    /**
     * roundtrip/header-name-normalised.
     */
    public function testHeaderNameNormalised(): void
    {
        $source = $this->response()
            ->withHeader('ETag', '"abc"')
            ->withHeader('X-PSR-7', '1')
            ->withHeader('X_Foo', 'a')
            ->withHeader('X-Foo', 'b');

        $back = $this->roundTrip($source);

        self::assertSame(['Etag', 'X-Psr-7', 'X-Foo'], array_keys($back->getHeaders()));
        self::assertSame('b', $back->getHeaderLine('X-Foo'));
    }

    /**
     * roundtrip/header-values-joined.
     */
    public function testHeaderValuesJoined(): void
    {
        $back = $this->roundTrip($this->response()->withHeader('X-Multi', ['a', 'b']));

        self::assertSame(['a, b'], $back->getHeader('X-Multi'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function contentTypeProvider(): array
    {
        return [
            'text' => ['text/html', 'text/html; charset=utf-8'],
            'xml' => ['application/xml', 'application/xml; charset=utf-8'],
            'json' => ['application/json', 'application/json'],
            'own charset' => ['text/html; charset=ISO-8859-1', 'text/html; charset=ISO-8859-1'],
        ];
    }

    /**
     * roundtrip/content-type-charset.
     *
     * @dataProvider contentTypeProvider
     */
    public function testContentTypeCharset(string $written, string $expected): void
    {
        $back = $this->roundTrip($this->response()->withHeader('Content-Type', $written));

        self::assertSame($expected, $back->getHeaderLine('Content-Type'));
    }

    /**
     * roundtrip/content-type-charset: a charset in the written type changes symfony's charset option.
     */
    public function testContentTypeCharsetChangesTheOption(): void
    {
        $target = SymfonyObjects::response();

        $this->roundTrip($this->response()->withHeader('Content-Type', 'text/html; charset=ISO-8859-1'), $target);

        self::assertSame('ISO-8859-1', $target->getCharset());
    }

    /**
     * roundtrip/protocol-version.
     */
    public function testProtocolVersion(): void
    {
        $source = $this->response()->withProtocolVersion('1.1');

        $back = $this->roundTrip($source, SymfonyObjects::response());

        self::assertSame('1.0', $back->getProtocolVersion());
    }

    /**
     * roundtrip/cookie-same-name.
     */
    public function testCookieSameName(): void
    {
        $source = $this->response()
            ->withAddedHeader('Set-Cookie', 'a=1')
            ->withAddedHeader('Set-Cookie', 'a=2');

        $back = $this->roundTrip($source);

        self::assertSame(['a=2'], $back->getHeader('Set-Cookie'));
    }

    /**
     * roundtrip/cookie-max-age.
     */
    public function testCookieMaxAge(): void
    {
        $back = $this->roundTrip($this->response()->withHeader('Set-Cookie', 'a=b; Max-Age=60'));

        self::assertSame(['a=b; Expires=Thu, 01 Jan 2026 00:01:00 GMT'], $back->getHeader('Set-Cookie'));
    }

    public function testCookieWithoutPathStaysWithoutPath(): void
    {
        $back = $this->roundTrip($this->response()->withHeader('Set-Cookie', 'a=b'));
        self::assertSame(['a=b'], $back->getHeader('Set-Cookie'));

        $back = $this->roundTrip($this->response()->withHeader('Set-Cookie', 'a=b; Path=/'));
        self::assertSame(['a=b; Path=/'], $back->getHeader('Set-Cookie'));
    }

    /**
     * roundtrip/cookie-unknown-attribute.
     */
    public function testCookieUnknownAttribute(): void
    {
        $back = $this->roundTrip($this->response()->withHeader('Set-Cookie', 'a=b; Priority=High'));

        self::assertSame(['a=b'], $back->getHeader('Set-Cookie'));
    }

    /**
     * roundtrip/reason-phrase-default: the part that does not depend on the PSR-7 implementation.
     * The default phrase for an empty one is pinned in the subclasses.
     */
    public function testReasonPhraseOwn(): void
    {
        $back = $this->roundTrip($this->factories()->response->createResponse(200, 'Fine'));

        self::assertSame('Fine', $back->getReasonPhrase());
    }

    /**
     * roundtrip/body-new-stream.
     */
    public function testBodyNewStream(): void
    {
        $source = $this->response()->withBody($this->factories()->stream->createStream('payload'));

        $back = $this->roundTrip($source);

        self::assertNotSame($source->getBody(), $back->getBody());
        self::assertSame('payload', (string) $back->getBody());
        self::assertSame('payload', (string) $source->getBody());
    }

    /**
     * roundtrip/no-content-header-only.
     */
    public function testNoContentHeaderOnly(): void
    {
        $target = SymfonyObjects::response(['http_protocol' => 'HTTP/1.1']);

        $back = $this->roundTrip($this->factories()->response->createResponse(204), $target);

        self::assertTrue($target->isHeaderOnly());
        self::assertSame(204, $back->getStatusCode());
    }

    public function testSymfonyResponseIntoTheSameObject(): void
    {
        $response = SymfonyObjects::response();
        $response->setStatusCode(201);
        $response->setHttpHeader('X-Foo', 'bar');
        $response->setContentType('text/html');
        $response->setCookie('a', '1');
        $response->setCookie('n', 'v', 1445412480, '/', '', true, true, 'Lax');
        $response->setCookie('p', '1', null, '');
        $response->setContent('payload');
        $status = $response->getStatusCode();
        $text = $response->getStatusText();
        $headers = $response->getHttpHeaders();
        $cookies = $response->getCookies();
        $content = $response->getContent();

        $converted = (new ResponseConverter($this->factories()->response, $this->factories()->stream))->convert($response);
        $this->writer()->write($converted, $response);

        self::assertSame($status, $response->getStatusCode());
        self::assertSame($text, $response->getStatusText());
        self::assertSame($headers, $response->getHttpHeaders());
        self::assertSame($cookies, $response->getCookies());
        self::assertSame($content, $response->getContent());
    }

    protected function response(): ResponseInterface
    {
        return $this->factories()->response->createResponse(200);
    }

    /**
     * Writes the source into the target with a fixed clock and converts the target back.
     */
    protected function roundTrip(ResponseInterface $source, ?\sfWebResponse $target = null): ResponseInterface
    {
        $target = $target ?? SymfonyObjects::response(['http_protocol' => 'HTTP/1.1']);

        $this->writer()->write($source, $target);

        return (new ResponseConverter($this->factories()->response, $this->factories()->stream))->convert($target);
    }

    private function writer(): PublicResponseWriter
    {
        return new PublicResponseWriter(static function (): \DateTimeImmutable {
            return new \DateTimeImmutable('@1767225600');
        });
    }
}
