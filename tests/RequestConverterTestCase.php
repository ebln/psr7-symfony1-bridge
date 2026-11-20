<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests;

use ebln\Symfony1\Psr7Bridge\ConversionFailed;
use ebln\Symfony1\Psr7Bridge\RequestConverter;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;
use ebln\Symfony1\Psr7Bridge\Tests\Support\SymfonyObjects;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

abstract class RequestConverterTestCase extends TestCase
{
    private string $file = '';

    abstract protected function factories(): Factories;

    protected function setUp(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'upload');
        self::assertIsString($file);
        file_put_contents($file, 'content');
        $this->file = $file;
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testMethodUriAndRequestTarget(): void
    {
        $request = SymfonyObjects::request([
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'example.org:8443',
            'HTTPS' => 'on',
            'SERVER_PORT' => '8443',
            'REQUEST_URI' => '/a/b?x=1#frag',
        ]);
        $expectedUri = $request->getUri();
        $converted = $this->convertRequest($request);

        self::assertSame('POST', $converted->getMethod());
        self::assertSame($expectedUri, (string) $converted->getUri());
        self::assertSame('example.org', $converted->getUri()->getHost());
        self::assertSame(8443, $converted->getUri()->getPort());
        self::assertSame('x=1', $converted->getUri()->getQuery());
        self::assertSame('frag', $converted->getUri()->getFragment());
        self::assertSame('/a/b?x=1', $converted->getRequestTarget());
    }

    public function testMissingHostThrows(): void
    {
        try {
            $this->convert(['HTTP_HOST' => null, 'SERVER_NAME' => null]);
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            // The URI "http:///" is rejected by the PSR-7 implementation inside createServerRequest().
            self::assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
        }
    }

    public function testUriWithoutHostIsRejectedByTheBridge(): void
    {
        // The implementation accepts "http:/a" and the host is empty, so the bridge's own check applies.
        try {
            $this->convert(['REQUEST_URI' => 'http:/a']);
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertSame('The symfony request has no host', $e->getMessage());
            self::assertNull($e->getPrevious());
        }
    }

    public function testServerParamsAreUnchanged(): void
    {
        $request = SymfonyObjects::request(['X_CUSTOM' => '1']);
        $expected = $request->getPathInfoArray();

        self::assertSame($expected, $this->convertRequest($request)->getServerParams());
    }

    public function testHeadersComeFromServerValues(): void
    {
        $converted = $this->convert([
            'HTTP_X_TEST' => 'a',
            'HTTP_ACCEPT' => 'foo, bar',
            'HTTP_X_PATH' => 'C:\dir, b',
            'CONTENT_TYPE' => 'application/json',
        ]);

        self::assertSame(['a'], $converted->getHeader('x-test'));
        self::assertSame(['foo, bar'], $converted->getHeader('Accept'));
        self::assertSame('C:\dir, b', $converted->getHeaderLine('x-path'));
        self::assertSame('application/json', $converted->getHeaderLine('content-type'));
    }

    public function testHeaderNameOfDigitsOnly(): void
    {
        $converted = $this->convert(['HTTP_123' => 'x']);

        self::assertSame('x', $converted->getHeaderLine('123'));
    }

    public function testHttpContentTypeAlone(): void
    {
        $converted = $this->convert(['HTTP_CONTENT_TYPE' => 'c/d']);

        self::assertTrue($converted->hasHeader('Content-Type'));
        self::assertSame(['c/d'], $converted->getHeader('content-type'));
        self::assertSame('c/d', $converted->getHeaderLine('Content-Type'));
        $names = array_map('strtolower', array_keys($converted->getHeaders()));
        self::assertSame(['content-type'], array_values(array_filter($names, static function (string $name): bool {
            return 'content-type' === $name;
        })));
    }

    public function testProtocolVersion(): void
    {
        self::assertSame('1.0', $this->convert(['SERVER_PROTOCOL' => 'HTTP/1.0'])->getProtocolVersion());
        self::assertSame('1.1', $this->convert(['SERVER_PROTOCOL' => null])->getProtocolVersion());
    }

    public function testEmptyBody(): void
    {
        $body = $this->convert()->getBody();

        self::assertSame('', (string) $body);
        self::assertTrue($body->isReadable());
        self::assertTrue($body->isSeekable());
    }

    public function testBody(): void
    {
        $body = $this->convert([], [], [], [], [], '{"a":1}')->getBody();

        self::assertSame(0, $body->tell());
        self::assertSame('{"a":1}', $body->getContents());
    }

    public function testQueryParams(): void
    {
        $query = ['x' => '1', 'y' => ['a', 'b']];

        self::assertSame($query, $this->convert([], $query)->getQueryParams());
    }

    /**
     * @return array<string, array{string}>
     */
    public function formContentTypes(): array
    {
        return [
            'urlencoded with charset' => ['application/x-www-form-urlencoded; charset=UTF-8'],
            'multipart in mixed case' => ['Multipart/Form-Data; boundary=x'],
        ];
    }

    /**
     * @dataProvider formContentTypes
     */
    public function testParsedBodyForFormContentTypes(string $contentType): void
    {
        $converted = $this->convert(['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => $contentType], [], ['a' => 'b']);

        self::assertSame(['a' => 'b'], $converted->getParsedBody());
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public function otherContentTypes(): array
    {
        return [
            'json' => [['CONTENT_TYPE' => 'application/json']],
            'none' => [[]],
        ];
    }

    /**
     * @param array<string, string> $server
     *
     * @dataProvider otherContentTypes
     */
    public function testNoParsedBodyForOtherContentTypes(array $server): void
    {
        $converted = $this->convert(['REQUEST_METHOD' => 'POST'] + $server, [], ['a' => 'b']);

        self::assertNull($converted->getParsedBody());
    }

    public function testCookieParams(): void
    {
        self::assertSame(['a' => 'b'], $this->convert([], [], [], ['a' => 'b'])->getCookieParams());
    }

    public function testUploadedFiles(): void
    {
        $leaf = ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => $this->file, 'error' => 0, 'size' => '7'];

        $files = $this->convert([], [], [], [], ['a' => ['b' => $leaf]])->getUploadedFiles();

        self::assertIsArray($files['a']);
        self::assertInstanceOf(UploadedFileInterface::class, $files['a']['b']);
    }

    public function testNoAttributes(): void
    {
        self::assertSame([], $this->convert()->getAttributes());
    }

    public function testLaterChangesAreNotVisible(): void
    {
        $converted = $this->convert(['HTTP_X_TEST' => 'before'], ['x' => 'before']);

        $_GET['x'] = 'changed';
        $_SERVER['HTTP_X_TEST'] = 'changed';

        self::assertSame(['x' => 'before'], $converted->getQueryParams());
        self::assertSame(['before'], $converted->getHeader('x-test'));
        self::assertSame('before', $converted->getServerParams()['HTTP_X_TEST']);
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $files
     */
    protected function convert(
        array $server = [],
        array $get = [],
        array $post = [],
        array $cookies = [],
        array $files = [],
        ?string $content = null
    ): ServerRequestInterface {
        return $this->convertRequest(SymfonyObjects::request($server, $get, $post, $cookies, $files, $content));
    }

    protected function convertRequest(\sfWebRequest $request): ServerRequestInterface
    {
        $factories = $this->factories();

        return (new RequestConverter($factories->serverRequest, $factories->stream, $factories->uploadedFile))->convert($request);
    }
}
