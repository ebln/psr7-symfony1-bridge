<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests;

use ebln\Symfony1\Psr7Bridge\ConversionFailed;
use ebln\Symfony1\Psr7Bridge\PublicResponseWriter;
use ebln\Symfony1\Psr7Bridge\ResponseWriter;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;
use ebln\Symfony1\Psr7Bridge\Tests\Support\SymfonyObjects;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

abstract class PublicResponseWriterTestCase extends TestCase
{
    private const FIXED_TIME = 1767225600;

    abstract protected function factories(): Factories;

    /**
     * @return array<string, array{int, string}>
     */
    public function statusProvider(): array
    {
        return [
            'not found' => [404, 'Not Found'],
            'permanent redirect' => [308, 'Permanent Redirect'],
            'teapot' => [418, "I'm a teapot"],
            'own phrase' => [200, 'Fine'],
            'empty phrase' => [200, ''],
        ];
    }

    /**
     * @dataProvider statusProvider
     */
    public function testStatusAndReasonPhrase(int $code, string $phrase): void
    {
        $source = $this->factories()->response->createResponse($code, $phrase);
        $target = SymfonyObjects::response();

        $this->writer()->write($source, $target);

        self::assertSame($code, $target->getStatusCode());
        self::assertSame($source->getReasonPhrase(), $target->getStatusText());
    }

    public function testNoContentSetsHeaderOnly(): void
    {
        $target = SymfonyObjects::response();
        $writer = $this->writer();

        $writer->write($this->factories()->response->createResponse(204), $target);
        self::assertTrue($target->isHeaderOnly());

        $writer->write($this->factories()->response->createResponse(200), $target);
        self::assertTrue($target->isHeaderOnly());
    }

    public function testOtherStatusLeavesHeaderOnlyOff(): void
    {
        $target = SymfonyObjects::response();

        $this->writer()->write($this->factories()->response->createResponse(200), $target);

        self::assertFalse($target->isHeaderOnly());
    }

    public function testHeaderNamesAsStoredBySymfony(): void
    {
        $source = $this->response()
            ->withHeader('CONTENT-type', 'application/json')
            ->withHeader('X-PSR-7-default', '1');
        $target = SymfonyObjects::response();

        $this->writer()->write($source, $target);

        self::assertSame(['Content-Type' => 'application/json', 'X-Psr-7-Default' => '1'], $target->getHttpHeaders());
    }

    public function testTextContentTypeGetsTheCharsetOfSymfony(): void
    {
        $source = $this->response()->withHeader('Content-Type', 'text/html');
        $target = SymfonyObjects::response();

        $this->writer()->write($source, $target);

        self::assertSame(['Content-Type' => 'text/html; charset=utf-8'], $target->getHttpHeaders());
    }

    public function testCharsetOfAnEarlierWriteStaysForTheNextTextContentType(): void
    {
        $target = SymfonyObjects::response();
        $writer = $this->writer();

        $writer->write($this->response()->withHeader('Content-Type', 'application/json; charset=iso-8859-1'), $target);
        $writer->write($this->response()->withHeader('Content-Type', 'text/html'), $target);

        self::assertSame(['Content-Type' => 'text/html; charset=iso-8859-1'], $target->getHttpHeaders());
    }

    public function testValuesOfOneNameAreJoined(): void
    {
        $source = $this->response()
            ->withHeader('X-Multi', ['a', 'b'])
            ->withHeader('X-Zero', ['0', 'b']);
        $target = SymfonyObjects::response();

        $this->writer()->write($source, $target);

        self::assertSame('a, b', $target->getHttpHeaders()['X-Multi']);
        self::assertSame('0, b', $target->getHttpHeaders()['X-Zero']);
    }

    public function testEmptyHeaderValue(): void
    {
        $source = $this->response()->withHeader('X-Empty', '');
        $target = SymfonyObjects::response();

        $this->writer()->write($source, $target);

        self::assertSame('', $target->getHttpHeaders()['X-Empty']);
    }

    public function testNumericHeaderName(): void
    {
        $source = $this->response()->withHeader('123', 'x');
        $target = SymfonyObjects::response();

        $this->writer()->write($source, $target);

        self::assertSame(['123' => 'x'], $target->getHttpHeaders());
    }

    public function testSetCookieIsNeverAHeader(): void
    {
        $source = $this->response()
            ->withHeader('set-cookie', 'a=1')
            ->withAddedHeader('Set-Cookie', 'b=2');
        $target = SymfonyObjects::response();

        $this->writer()->write($source, $target);

        self::assertArrayHasKey('set-cookie', $source->getHeaders());
        self::assertArrayNotHasKey('Set-Cookie', $target->getHttpHeaders());
        self::assertSame(['a', 'b'], array_keys($target->getCookies()));
    }

    /**
     * @return array<string, array{string}>
     */
    public function symfonySetCookieNameProvider(): array
    {
        return [
            'underscore' => ['Set_Cookie'],
            'capitals' => ['SET_COOKIE'],
            'lower case' => ['set_cookie'],
        ];
    }

    /**
     * Symfony stores `Set_Cookie` as `Set-Cookie`. Such a header would bypass the cookie checks.
     *
     * @dataProvider symfonySetCookieNameProvider
     */
    public function testHeaderThatSymfonyStoresAsSetCookieFails(string $name): void
    {
        $source = $this->factories()->response->createResponse(404)
            ->withHeader('X-Foo', 'bar')
            ->withBody($this->factories()->stream->createStream('payload'))
            ->withHeader($name, 'x=y z; Domain=evil.example')
            ->withHeader('Set-Cookie', 'a=1');
        $target = SymfonyObjects::response();

        $this->assertWriteFailsAsSetCookie($source, $target, $name);
    }

    /**
     * @return array<string, array{string}>
     */
    public function colonSetCookieNameProvider(): array
    {
        return [
            'colon' => ['Set-Cookie:x'],
            'underscore and colon' => ['set_cookie:x'],
            'space and colon' => ['Set Cookie:x'],
            'colon at the end' => ['Set-Cookie:'],
        ];
    }

    /**
     * PHP's `header()` would send `Set-Cookie:x: value` as a `Set-Cookie` header.
     *
     * @dataProvider colonSetCookieNameProvider
     */
    public function testHeaderNameWhosePartBeforeTheColonIsSetCookieFails(string $name): void
    {
        $target = SymfonyObjects::response();

        $this->assertWriteFailsAsSetCookie($this->sourceWithHeaders([$name => ['v']]), $target, $name);
    }

    /**
     * Symfony stores `Set Cookie` as `Set-Cookie`. The source is a mock, because the PSR-7
     * implementations under test reject a name with a space.
     */
    public function testHeaderNameWithASpaceThatSymfonyStoresAsSetCookieFails(): void
    {
        $target = SymfonyObjects::response();

        $this->assertWriteFailsAsSetCookie($this->sourceWithHeaders(['Set Cookie' => ['v']]), $target, 'Set Cookie');
    }

    /**
     * @return array<string, array{string}>
     */
    public function otherHeaderNameProvider(): array
    {
        return [
            'digit suffix' => ['Set-Cookie2'],
            'prefix' => ['X-Set-Cookie'],
            'colon after another name' => ['X:Set-Cookie'],
            'dash before the colon' => ['Set-Cookie-:x'],
        ];
    }

    /**
     * @dataProvider otherHeaderNameProvider
     */
    public function testHeaderNameThatIsNotSentAsSetCookieIsWritten(string $name): void
    {
        $target = SymfonyObjects::response();

        $this->writer()->write($this->sourceWithHeaders([$name => ['v']]), $target);

        self::assertSame([], $target->getCookies());
        self::assertCount(1, $target->getHttpHeaders());
        self::assertSame('v', $target->getHttpHeader($name));
    }

    public function testExistingStateIsKept(): void
    {
        $target = SymfonyObjects::response();
        $target->setHttpHeader('X-Keep', '1');
        $target->setHttpHeader('X-Foo', 'old');
        $target->setCookie('old', 'x');
        $source = $this->response()
            ->withHeader('X-Foo', 'new')
            ->withHeader('Set-Cookie', 'a=1');

        $this->writer()->write($source, $target);

        self::assertSame('1', $target->getHttpHeaders()['X-Keep']);
        self::assertSame('new', $target->getHttpHeaders()['X-Foo']);
        self::assertSame(['old', 'a'], array_keys($target->getCookies()));
    }

    public function testCookieFields(): void
    {
        $source = $this->response()->withHeader(
            'Set-Cookie',
            'sid=abc; Expires=Wed, 21 Oct 2015 07:28:00 GMT; Path=/app; Domain=example.org; Secure; HttpOnly; SameSite=Strict'
        );
        $target = SymfonyObjects::response();

        $this->writer()->write($source, $target);

        self::assertSame(
            [
                'name' => 'sid',
                'value' => 'abc',
                'expire' => 1445412480,
                'path' => '/app',
                'domain' => 'example.org',
                'secure' => true,
                'httpOnly' => true,
                'samesite' => 'Strict',
            ],
            self::cookie($target, 'sid')
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public function cookieWithoutPathProvider(): array
    {
        return [
            'no Path' => ['a=b'],
            'empty Path' => ['a=b; Path='],
        ];
    }

    /**
     * @dataProvider cookieWithoutPathProvider
     */
    public function testCookieWithoutPathIsStoredWithAnEmptyPath(string $header): void
    {
        $source = $this->response()->withHeader('Set-Cookie', $header);
        $target = SymfonyObjects::response();

        $this->writer()->write($source, $target);

        self::assertSame('', self::cookie($target, 'a')['path']);
        self::assertNull(self::cookie($target, 'a')['expire']);
    }

    public function testCookieWithoutPathReplacesAStoredCookieWithTheRootPath(): void
    {
        $target = SymfonyObjects::response();
        $target->setCookie('a', 'old'); // symfony stores the path '/'

        $this->writer()->write($this->response()->withHeader('Set-Cookie', 'a=b'), $target);

        self::assertSame('b', self::cookie($target, 'a')['value']);
        self::assertSame('', self::cookie($target, 'a')['path']);
    }

    public function testLastCookieOfANameWins(): void
    {
        $source = $this->response()
            ->withAddedHeader('Set-Cookie', 'a=1')
            ->withAddedHeader('Set-Cookie', 'a=2');
        $target = SymfonyObjects::response();

        $this->writer()->write($source, $target);

        self::assertCount(1, $target->getCookies());
        self::assertSame('2', self::cookie($target, 'a')['value']);
    }

    public function testMaxAgeUsesTheInjectedClock(): void
    {
        $calls = 0;
        $writer = new PublicResponseWriter(static function () use (&$calls): \DateTimeImmutable {
            ++$calls;

            return new \DateTimeImmutable('@'.self::FIXED_TIME);
        });
        $source = $this->response()
            ->withAddedHeader('Set-Cookie', 'a=b; Max-Age=60')
            ->withAddedHeader('Set-Cookie', 'c=d; Max-Age=120');
        $target = SymfonyObjects::response();

        $writer->write($source, $target);

        self::assertSame(self::FIXED_TIME + 60, self::cookie($target, 'a')['expire']);
        self::assertSame(self::FIXED_TIME + 120, self::cookie($target, 'c')['expire']);
        self::assertSame(1, $calls);
    }

    public function testClockIsAskedOncePerWrite(): void
    {
        $calls = 0;
        $writer = new PublicResponseWriter(static function () use (&$calls): \DateTimeImmutable {
            ++$calls;

            return new \DateTimeImmutable('@'.self::FIXED_TIME);
        });
        $source = $this->response()->withHeader('Set-Cookie', 'a=b; Max-Age=60');

        $writer->write($source, SymfonyObjects::response());
        $writer->write($source, SymfonyObjects::response());

        self::assertSame(2, $calls);
    }

    public function testClockIsNotCalledWithoutMaxAge(): void
    {
        $calls = 0;
        $writer = new PublicResponseWriter(static function () use (&$calls): \DateTimeImmutable {
            ++$calls;

            return new \DateTimeImmutable('@'.self::FIXED_TIME);
        });
        $source = $this->response()->withHeader('Set-Cookie', 'a=b');

        $writer->write($source, SymfonyObjects::response());

        self::assertSame(0, $calls);
    }

    public function testDefaultClockIsTheSystemTime(): void
    {
        $source = $this->response()->withHeader('Set-Cookie', 'a=b; Max-Age=60');
        $target = SymfonyObjects::response();

        $before = time();
        (new PublicResponseWriter())->write($source, $target);
        $after = time();

        $expire = self::cookie($target, 'a')['expire'];
        self::assertIsInt($expire);
        self::assertGreaterThanOrEqual($before + 60, $expire);
        self::assertLessThanOrEqual($after + 60, $expire);
    }

    public function testClockCanBeAMethodOfAnObject(): void
    {
        $clock = new class {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('@1767225600');
            }
        };
        $source = $this->response()->withHeader('Set-Cookie', 'a=b; Max-Age=60');
        $target = SymfonyObjects::response();

        (new PublicResponseWriter([$clock, 'now']))->write($source, $target);

        self::assertSame(self::FIXED_TIME + 60, self::cookie($target, 'a')['expire']);
    }

    public function testClockWithAWrongReturnTypeFails(): void
    {
        $writer = new PublicResponseWriter(self::callable(static function (): string {
            return 'now';
        }));
        $source = $this->response()->withHeader('Set-Cookie', 'a=b; Max-Age=60');

        try {
            $writer->write($source, SymfonyObjects::response());
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertSame('The time source must return a DateTimeInterface', $e->getMessage());
        }
    }

    public function testClockCanReturnAMutableDateTime(): void
    {
        $writer = new PublicResponseWriter(static function (): \DateTime {
            return new \DateTime('@'.self::FIXED_TIME);
        });
        $source = $this->response()->withHeader('Set-Cookie', 'a=b; Max-Age=60');
        $target = SymfonyObjects::response();

        $writer->write($source, $target);

        self::assertSame(self::FIXED_TIME + 60, self::cookie($target, 'a')['expire']);
    }

    public function testClockIsAskedOnceForSeveralMaxAgeCookiesAfterAZeroOne(): void
    {
        $calls = 0;
        $writer = new PublicResponseWriter(static function () use (&$calls): \DateTimeImmutable {
            ++$calls;

            return new \DateTimeImmutable('@'.self::FIXED_TIME);
        });
        $source = $this->response()
            ->withAddedHeader('Set-Cookie', 'a=b; Max-Age=0')
            ->withAddedHeader('Set-Cookie', 'c=d; Max-Age=60');
        $target = SymfonyObjects::response();

        $writer->write($source, $target);

        self::assertSame(1, $calls);
        self::assertSame(1, self::cookie($target, 'a')['expire']);
        self::assertSame(self::FIXED_TIME + 60, self::cookie($target, 'c')['expire']);
    }

    public function testClockIsNotCalledForAZeroMaxAge(): void
    {
        $calls = 0;
        $writer = new PublicResponseWriter(static function () use (&$calls): \DateTimeImmutable {
            ++$calls;

            return new \DateTimeImmutable('@'.self::FIXED_TIME);
        });
        $source = $this->response()->withHeader('Set-Cookie', 'a=b; Max-Age=0');

        $writer->write($source, SymfonyObjects::response());

        self::assertSame(0, $calls);
    }

    public function testClockThatThrowsFails(): void
    {
        $exception = new \RuntimeException('no time');
        $writer = new PublicResponseWriter(static function () use ($exception): \DateTimeImmutable {
            throw $exception;
        });
        $source = $this->factories()->response->createResponse(404)
            ->withHeader('X-Foo', 'bar')
            ->withBody($this->factories()->stream->createStream('payload'))
            ->withAddedHeader('Set-Cookie', 'ok=1')
            ->withAddedHeader('Set-Cookie', 'a=b; Max-Age=60');
        $target = SymfonyObjects::response();

        try {
            $writer->write($source, $target);
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertSame($exception, $e->getPrevious());
        }
        $this->assertTargetUntouched($target);
    }

    public function testStoredExpireIsAnInteger(): void
    {
        $source = $this->response()->withHeader('Set-Cookie', 'a=b; Max-Age=60');
        $target = SymfonyObjects::response();

        $this->writer()->write($source, $target);

        self::assertIsInt(self::cookie($target, 'a')['expire']);
    }

    public function testBody(): void
    {
        $source = $this->response()->withBody($this->factories()->stream->createStream('payload'));
        $target = SymfonyObjects::response();

        $this->writer()->write($source, $target);

        self::assertSame('payload', $target->getContent());
    }

    /**
     * @return array<string, array{string}>
     */
    public function unsendableCookieProvider(): array
    {
        return [
            'space in value' => ['a=b c'],
            'comma in value' => ['a=b,c'],
            'comma-joined line' => ['a=1, b=2'],
            'space in name' => ['a b=1'],
            'space in path' => ['a=1; Path=/x y'],
            'space in domain' => ['a=1; Domain=a b'],
            'space in samesite' => ['a=1; SameSite=a b'],
            'tab in value' => ["a=b\tc"],
        ];
    }

    /**
     * @dataProvider unsendableCookieProvider
     */
    public function testCookieThatPhpCannotSendFailsBeforeAnyWrite(string $cookie): void
    {
        $source = $this->factories()->response->createResponse(404)
            ->withHeader('X-Foo', 'bar')
            ->withBody($this->factories()->stream->createStream('payload'))
            ->withAddedHeader('Set-Cookie', 'ok=1')
            ->withAddedHeader('Set-Cookie', $cookie);
        $target = SymfonyObjects::response();

        try {
            $this->writer()->write($source, $target);
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertStringContainsString('separator character', $e->getMessage());
            self::assertStringNotContainsString('payload', $e->getMessage());
        }

        $this->assertTargetUntouched($target);
    }

    public function testMessageNamesTheCookieAndTheFieldButNotTheText(): void
    {
        $source = $this->response()->withHeader('Set-Cookie', 'sid=secret value');

        try {
            $this->writer()->write($source, SymfonyObjects::response());
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertStringContainsString('"sid"', $e->getMessage());
            self::assertStringContainsString('its value holds', $e->getMessage());
            self::assertStringNotContainsString('secret', $e->getMessage());
        }
    }

    public function testMessageForABadNameDoesNotPrintTheName(): void
    {
        $source = $this->response()->withHeader('Set-Cookie', 'my secret=1');

        try {
            $this->writer()->write($source, SymfonyObjects::response());
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertStringContainsString('its cookie name holds', $e->getMessage());
            self::assertStringNotContainsString('secret', $e->getMessage());
        }
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public function sendableCookieProvider(): array
    {
        return [
            'equals sign in value' => ['a=b=c', 'value', 'b=c'],
            'percent encoding' => ['a=%20x', 'value', '%20x'],
            'path' => ['a=1; Path=/a/b', 'path', '/a/b'],
            'domain with a dot' => ['a=1; Domain=.example.org', 'domain', '.example.org'],
        ];
    }

    /**
     * @dataProvider sendableCookieProvider
     */
    public function testCookieWithoutSeparatorsIsWritten(string $cookie, string $field, string $expected): void
    {
        $source = $this->response()->withHeader('Set-Cookie', $cookie);
        $target = SymfonyObjects::response();

        $this->writer()->write($source, $target);

        self::assertSame($expected, self::cookie($target, 'a')[$field]);
    }

    public function testBodyIsWrittenFromTheStart(): void
    {
        $source = $this->response()->withBody($this->factories()->stream->createStream('payload'));
        $source->getBody()->getContents();
        $target = SymfonyObjects::response();

        $this->writer()->write($source, $target);

        self::assertSame('payload', $target->getContent());
    }

    public function testSourceStreamStaysOpen(): void
    {
        $source = $this->response()->withBody($this->factories()->stream->createStream('payload'));

        $this->writer()->write($source, SymfonyObjects::response());

        self::assertTrue($source->getBody()->isReadable());
        self::assertSame('payload', (string) $source->getBody());
    }

    public function testProtocolVersionIsNotWritten(): void
    {
        $source = $this->response()->withProtocolVersion('2.0');
        $target = SymfonyObjects::response();

        $this->writer()->write($source, $target);

        self::assertSame('HTTP/1.0', $target->getOptions()['http_protocol']);
    }

    public function testFailedWriteLeavesTheTargetUnchanged(): void
    {
        $source = $this->factories()->response->createResponse(404)
            ->withHeader('X-Foo', 'bar')
            ->withBody($this->factories()->stream->createStream('payload'))
            ->withAddedHeader('Set-Cookie', 'a=1')
            ->withAddedHeader('Set-Cookie', '=broken');
        $target = SymfonyObjects::response();

        try {
            $this->writer()->write($source, $target);
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertStringContainsString('no cookie name', $e->getMessage());
        }

        $this->assertTargetUntouched($target);
    }

    public function testUnreadableBodyLeavesTheTargetUnchanged(): void
    {
        $stream = $this->factories()->stream->createStream('payload');
        $source = $this->response()
            ->withStatus(404)
            ->withHeader('X-Foo', 'bar')
            ->withHeader('Set-Cookie', 'a=1')
            ->withBody($stream);
        $stream->close();
        $target = SymfonyObjects::response();

        try {
            $this->writer()->write($source, $target);
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertNotNull($e->getPrevious());
        }

        $this->assertTargetUntouched($target);
    }

    /**
     * @return array<string, array{string}>
     */
    public function failingReadProvider(): array
    {
        return [
            'headers' => ['getHeaders'],
            'status code' => ['getStatusCode'],
            'reason phrase' => ['getReasonPhrase'],
        ];
    }

    /**
     * @dataProvider failingReadProvider
     */
    public function testFailingReadOfTheSourceIsWrappedAndLeavesTheTargetUnchanged(string $method): void
    {
        $failure = new \RuntimeException('read failed');
        $source = $this->sourceWithHeaders([]);
        $source->method($method)->willThrowException($failure);
        $target = SymfonyObjects::response();

        try {
            $this->writer()->write($source, $target);
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertSame($failure, $e->getPrevious());
        }

        $this->assertTargetUntouched($target);
    }

    public function testConversionFailedOfTheSourceIsNotWrappedAgain(): void
    {
        $failure = ConversionFailed::because('own failure');
        $source = $this->createMock(ResponseInterface::class);
        $source->method('getHeader')->willThrowException($failure);

        try {
            $this->writer()->write($source, SymfonyObjects::response());
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertSame($failure, $e);
        }
    }

    protected function writer(): ResponseWriter
    {
        return new PublicResponseWriter();
    }

    protected function response(): ResponseInterface
    {
        return $this->factories()->response->createResponse(200);
    }

    /**
     * @return array<mixed>
     */
    private static function cookie(\sfWebResponse $target, string $name): array
    {
        $cookies = $target->getCookies();
        self::assertArrayHasKey($name, $cookies);
        self::assertIsArray($cookies[$name]);

        return $cookies[$name];
    }

    /**
     * @param array<string, array<string>> $headers
     *
     * @return ResponseInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function sourceWithHeaders(array $headers): ResponseInterface
    {
        $source = $this->createMock(ResponseInterface::class);
        $source->method('getHeader')->willReturn([]);
        $source->method('getHeaders')->willReturn($headers);
        $source->method('getBody')->willReturn($this->factories()->stream->createStream('payload'));
        $source->method('getStatusCode')->willReturn(404);
        $source->method('getReasonPhrase')->willReturn('Not Found');

        return $source;
    }

    private function assertWriteFailsAsSetCookie(ResponseInterface $source, \sfWebResponse $target, string $name): void
    {
        try {
            $this->writer()->write($source, $target);
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertStringContainsString('would be sent as Set-Cookie', $e->getMessage());
            self::assertStringNotContainsString($name, $e->getMessage());
        }

        $this->assertTargetUntouched($target);
    }

    private function assertTargetUntouched(\sfWebResponse $target): void
    {
        self::assertSame(200, $target->getStatusCode());
        self::assertSame([], $target->getHttpHeaders());
        self::assertSame([], $target->getCookies());
        self::assertSame('', $target->getContent());
    }

    /**
     * Hides the signature of a closure from PHPStan, so that a test can pass a wrong return type.
     */
    private static function callable(callable $callable): callable
    {
        return $callable;
    }
}
