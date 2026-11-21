<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Integration;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;

/**
 * Control for the tests that GuzzleResponseTest and GuzzleServerRequestTest skip.
 *
 * Each test of guzzle behaviour works on plain `guzzlehttp/psr7` objects, without the bridge, and asserts a behaviour
 * of guzzle that the external PSR-7 suite does not accept. When such a test fails, guzzle changed, and the matching
 * skip can go.
 *
 * testWarningInsideTheCallableReachesPhpunit and testDeprecationInsideTheCallableIsIgnored check the helper
 * withoutDeprecations(). They have no matching skip.
 *
 * Guzzle reports some of these behaviours as deprecated. The tests catch that notice themselves, so that the output
 * of the suite stays free of deprecations.
 */
final class GuzzleDeviationsTest extends TestCase
{
    /**
     * Skips: testWithHeaderInvalidArguments and testWithAddedHeaderInvalidArguments.
     *
     * @return array<string, array{string, mixed, array<array-key, mixed>}>
     */
    public function emptyHeaderValueProvider(): array
    {
        return [
            'withHeader with an empty array' => ['withHeader', [], []],
            'withHeader with false' => ['withHeader', false, ['']],
            'withAddedHeader with an empty array' => ['withAddedHeader', [], []],
            'withAddedHeader with false' => ['withAddedHeader', false, ['']],
        ];
    }

    /**
     * @param mixed                   $value    a header value that the PSR-7 suite rejects
     * @param array<array-key, mixed> $expected
     *
     * @dataProvider emptyHeaderValueProvider
     */
    public function testHeaderValueThatThePsrSuiteRejectsIsAccepted(string $method, $value, array $expected): void
    {
        $message = self::withoutDeprecations(static function () use ($method, $value) {
            return self::invoke(new Response(), $method, 'foo', $value);
        });

        self::assertInstanceOf(Response::class, $message);
        self::assertSame($expected, $message->getHeader('foo'));
    }

    public function testScalarParsedBodyIsAccepted(): void
    {
        $request = self::withoutDeprecations(static function () {
            return self::invoke(new ServerRequest('GET', 'http://example.org/'), 'withParsedBody', 'scalar');
        });

        self::assertInstanceOf(ServerRequest::class, $request);
        self::assertSame('scalar', $request->getParsedBody());
    }

    public function testMethodIsUpperCase(): void
    {
        $request = self::withoutDeprecations(static function () {
            return new ServerRequest('head', 'http://example.org/');
        });

        self::assertInstanceOf(ServerRequest::class, $request);
        self::assertSame('HEAD', $request->getMethod());
    }

    public function testRequestTargetKeepsTheLeadingSlashes(): void
    {
        $request = (new ServerRequest('GET', 'http://example.org/'))
            ->withUri(new Uri('http://example.org//valid///path'));

        self::assertSame('//valid///path', $request->getRequestTarget());
    }

    public function testWarningInsideTheCallableReachesPhpunit(): void
    {
        try {
            self::withoutDeprecations(static function (): void {
                trigger_error('probe', \E_USER_WARNING);
            });
            self::fail('PHPUnit did not receive the warning');
        } catch (\PHPUnit\Framework\Error\Warning $e) {
            self::assertSame('probe', $e->getMessage());
        }
    }

    public function testDeprecationInsideTheCallableIsIgnored(): void
    {
        self::assertSame(7, self::withoutDeprecations(static function (): int {
            trigger_error('probe', \E_USER_DEPRECATED);

            return 7;
        }));
    }

    /**
     * Calls a method with arguments that the PSR-7 docblock does not allow. Reflection keeps PHPStan from
     * checking the arguments, which is the point of these tests.
     *
     * @param mixed $arguments the arguments of the call
     *
     * @return mixed the result of the call
     */
    private static function invoke(object $subject, string $method, ...$arguments)
    {
        return (new \ReflectionMethod($subject, $method))->invokeArgs($subject, $arguments);
    }

    /**
     * Runs the callable and ignores the deprecation notices that it triggers. Every other error goes to the handler
     * that was installed before, which is the handler of PHPUnit. Without a previous handler, PHP handles it.
     *
     * @return mixed the result of the callable
     */
    private static function withoutDeprecations(callable $callable)
    {
        $previous = set_error_handler(static function (int $level, string $message, ...$rest) use (&$previous): bool {
            if (\E_USER_DEPRECATED === $level || \E_DEPRECATED === $level) {
                return true;
            }

            // PHP handles the error itself only when a handler returns exactly false. Any other result, null
            // included, means handled. The comparison keeps that meaning and gives the declared bool type.
            return \is_callable($previous) ? false !== $previous($level, $message, ...$rest) : false;
        });
        try {
            return $callable();
        } finally {
            restore_error_handler();
        }
    }
}
