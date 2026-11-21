<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests;

use ebln\Symfony1\Psr7Bridge\ConversionFailed;
use ebln\Symfony1\Psr7Bridge\ResponseConverter;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;
use ebln\Symfony1\Psr7Bridge\Tests\Support\SymfonyObjects;
use PHPUnit\Framework\TestCase;

abstract class ResponseConverterTestCase extends TestCase
{
    abstract protected function factories(): Factories;

    public function testStatusAndReasonPhrase(): void
    {
        $response = SymfonyObjects::response();

        $response->setStatusCode(404);
        $converted = $this->converter()->convert($response);
        self::assertSame(404, $converted->getStatusCode());
        self::assertSame('Not Found', $converted->getReasonPhrase());

        $response->setStatusCode(201, 'Made');
        $converted = $this->converter()->convert($response);
        self::assertSame(201, $converted->getStatusCode());
        self::assertSame('Made', $converted->getReasonPhrase());
    }

    public function testMissingStatusTextBecomesAString(): void
    {
        $response = SymfonyObjects::response();
        // Symfony has no text for 599: it raises a notice (PHP 7.4) or a warning (PHP 8) and stores null.
        @$response->setStatusCode(599);
        self::assertNull($response->getStatusText());

        $converted = $this->converter()->convert($response);

        self::assertSame(599, $converted->getStatusCode());
        self::assertSame('', $converted->getReasonPhrase());
    }

    public function testNumericStatusStringBecomesAnInteger(): void
    {
        $response = SymfonyObjects::response();
        $response->setStatusCode('404');
        self::assertSame('404', $response->getStatusCode());

        $converted = $this->converter()->convert($response);

        self::assertSame(404, $converted->getStatusCode());
        self::assertSame('Not Found', $converted->getReasonPhrase());
    }

    /**
     * @return array<string, array{string, int}>
     */
    public function partlyNumericStatusProvider(): array
    {
        return [
            'trailing space' => ['404 ', 404],
            'leading space' => [' 404', 404],
            'trailing text' => ['404abc', 404],
        ];
    }

    /**
     * The stored code is cast as PHP casts it, on every PHP version. is_numeric() differs between 7.4 and 8.
     *
     * @dataProvider partlyNumericStatusProvider
     */
    public function testStatusStringWithATrailingPartIsCast(string $stored, int $expected): void
    {
        $response = SymfonyObjects::response();
        $response->setStatusCode($stored, 'Odd');
        self::assertSame($stored, $response->getStatusCode());

        $converted = $this->converter()->convert($response);

        self::assertSame($expected, $converted->getStatusCode());
        self::assertSame('Odd', $converted->getReasonPhrase());
    }

    public function testStatusBounds(): void
    {
        $response = SymfonyObjects::response();

        $response->setStatusCode(100, 'Continue');
        self::assertSame(100, $this->converter()->convert($response)->getStatusCode());

        $response->setStatusCode(503);
        self::assertSame(503, $this->converter()->convert($response)->getStatusCode());
    }

    public function testOneValuePerHeader(): void
    {
        $response = SymfonyObjects::response();
        $response->setHttpHeader('X-List', 'a, b');
        $response->setHttpHeader('X-Empty', '');

        $converted = $this->converter()->convert($response);

        self::assertSame(['a, b'], $converted->getHeader('X-List'));
        self::assertSame([''], $converted->getHeader('X-Empty'));
    }

    public function testHeaderNamesAreReportedAsSymfonyStoresThem(): void
    {
        $response = SymfonyObjects::response();
        $response->setHttpHeader('x_psr_7_default', 'a');

        $converted = $this->converter()->convert($response);

        self::assertSame(['X-Psr-7-Default' => ['a']], $converted->getHeaders());
    }

    public function testHeaderNameOfDigitsOnly(): void
    {
        $response = SymfonyObjects::response();
        $response->setHttpHeader('123', 'v');

        $converted = $this->converter()->convert($response);

        self::assertSame(['v'], $converted->getHeader('123'));
    }

    public function testHeaderValuesThatAreNotStringsBecomeStrings(): void
    {
        $response = SymfonyObjects::response();
        // Symfony stores what it gets: an int stays an int. The docblock of setHttpHeader() says string.
        $this->replaceProperty($response, 'headers', ['X-Int' => 5, 'X-Float' => 1.5, 'X-True' => true]);

        $converted = $this->converter()->convert($response);

        self::assertSame(['5'], $converted->getHeader('X-Int'));
        self::assertSame(['1.5'], $converted->getHeader('X-Float'));
        self::assertSame(['1'], $converted->getHeader('X-True'));
    }

    public function testHeaderValueThatIsNotScalarBecomesAStringOrIsEmpty(): void
    {
        $response = SymfonyObjects::response();
        $stringable = new class {
            public function __toString(): string
            {
                return 'text';
            }
        };
        $this->replaceProperty($response, 'headers', [
            'X-Stringable' => $stringable,
            'X-Object' => new \stdClass(),
            'X-Array' => ['a'],
            'X-Null' => null,
        ]);

        $converted = $this->converter()->convert($response);

        self::assertSame(['text'], $converted->getHeader('X-Stringable'));
        self::assertSame([''], $converted->getHeader('X-Object'));
        self::assertSame([''], $converted->getHeader('X-Array'));
        self::assertSame([''], $converted->getHeader('X-Null'));
    }

    public function testCookiesBecomeSetCookieHeaders(): void
    {
        $response = SymfonyObjects::response();
        $response->setCookie('a', '1');
        $response->setCookie('n', 'v', 1445412480, '/', '', true, true, 'Lax');

        $converted = $this->converter()->convert($response);

        self::assertSame(
            ['a=1; Path=/', 'n=v; Expires=Wed, 21 Oct 2015 07:28:00 GMT; Path=/; Secure; HttpOnly; SameSite=Lax'],
            $converted->getHeader('Set-Cookie')
        );
    }

    public function testCookieThatCannotBeSerialisedFailsWithoutWrapping(): void
    {
        $response = SymfonyObjects::response();
        $response->setCookie('a', 'x; Domain=example.org');

        try {
            $this->converter()->convert($response);
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertStringStartsWith('The cookie "a" cannot be written as Set-Cookie', $e->getMessage());
            self::assertNull($e->getPrevious());
        }
    }

    public function testCookieEntryThatIsNotAnArrayIsSkipped(): void
    {
        $response = SymfonyObjects::response();
        $response->setCookie('a', '1');
        $this->replaceProperty($response, 'cookies', ['junk', null, 5, ['name' => 'b', 'value' => '2']]);

        $converted = $this->converter()->convert($response);

        self::assertSame(['b=2'], $converted->getHeader('Set-Cookie'));
    }

    public function testBody(): void
    {
        $response = SymfonyObjects::response();
        $response->setContent('payload');

        $converted = $this->converter()->convert($response);

        self::assertSame('payload', (string) $converted->getBody());
    }

    public function testBodyThatIsNotAStringBecomesAString(): void
    {
        $response = SymfonyObjects::response();
        $this->replaceProperty($response, 'content', null);
        self::assertSame('', (string) $this->converter()->convert($response)->getBody());

        $this->replaceProperty($response, 'content', 42);
        self::assertSame('42', (string) $this->converter()->convert($response)->getBody());
    }

    public function testProtocolVersion(): void
    {
        self::assertSame('1.0', $this->converter()->convert(SymfonyObjects::response())->getProtocolVersion());
        self::assertSame(
            '1.1',
            $this->converter()->convert(SymfonyObjects::response(['http_protocol' => 'HTTP/1.1']))->getProtocolVersion()
        );
    }

    public function testSendTimeDefaultsAreNotIncluded(): void
    {
        $converted = $this->converter()->convert(SymfonyObjects::response());

        self::assertFalse($converted->hasHeader('Content-Type'));
        self::assertSame([], $converted->getHeaders());
    }

    public function testLaterChangesAreNotVisible(): void
    {
        $response = SymfonyObjects::response();
        $converted = $this->converter()->convert($response);

        $response->setHttpHeader('X-Late', '1');
        $response->setContent('late');

        self::assertFalse($converted->hasHeader('X-Late'));
        self::assertSame('', (string) $converted->getBody());
    }

    public function testRejectedHeaderValueFails(): void
    {
        $response = SymfonyObjects::response();
        $response->setHttpHeader('X-Bad', "a\r\nb");

        try {
            $this->converter()->convert($response);
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
            self::assertStringStartsWith('The symfony response cannot be converted', $e->getMessage());
        }
    }

    protected function converter(): ResponseConverter
    {
        $factories = $this->factories();

        return new ResponseConverter($factories->response, $factories->stream);
    }

    /**
     * Symfony stores what its setters get and its setters have narrower docblocks than their code. This puts a value
     * in the property that a caller of a loosely typed setter, or a subclass, could have put there.
     *
     * @param mixed $value the value for the property
     */
    protected function replaceProperty(\sfWebResponse $response, string $name, $value): void
    {
        $property = new \ReflectionProperty(\sfWebResponse::class, $name);
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $property->setValue($response, $value);
    }
}
