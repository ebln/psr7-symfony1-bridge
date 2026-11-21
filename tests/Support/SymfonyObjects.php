<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Support;

/**
 * Builds symfony1 web objects without a symfony1 project.
 *
 * The request does not copy the superglobals. It reads $_SERVER, $_COOKIE and $_FILES when a getter
 * is called, and request() replaces the superglobals. Therefore use one request at a time and finish
 * with it before the next request() call. Never call request() in a data provider: providers run
 * before PHPUnit saves the superglobals for a test. Pass arrays from the provider and build the
 * request inside the test.
 */
final class SymfonyObjects
{
    private const SERVER_DEFAULTS = [
        'REQUEST_METHOD' => 'GET',
        'HTTP_HOST' => 'example.org',
        'SERVER_NAME' => 'example.org',
        'SERVER_PORT' => '80',
        'REQUEST_URI' => '/',
        'SCRIPT_NAME' => '/index.php',
        'SERVER_PROTOCOL' => 'HTTP/1.1',
    ];

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $files
     * @param array<string, mixed> $options the options of sfWebRequest, for example trust_proxy
     */
    public static function request(
        array $server = [],
        array $get = [],
        array $post = [],
        array $cookies = [],
        array $files = [],
        ?string $content = null,
        array $options = []
    ): \sfWebRequest {
        $_SERVER = array_filter(
            array_merge(self::SERVER_DEFAULTS, $server),
            static function ($value): bool {
                return null !== $value;
            }
        );
        $_GET = $get;
        $_POST = $post;
        $_COOKIE = $cookies;
        $_FILES = $files;

        if (null === $content) {
            return new \sfWebRequest(new \sfEventDispatcher(), [], [], $options);
        }

        // The constructor only calls initialize(), and initialize() reads the content for PUT, PATCH
        // and DELETE. The content must be set before that call.
        $request = (new \ReflectionClass(\sfWebRequest::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(\sfWebRequest::class, 'content');
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $property->setValue($request, $content);
        $request->initialize(new \sfEventDispatcher(), [], [], $options);

        return $request;
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function response(array $options = []): \sfWebResponse
    {
        return new \sfWebResponse(new \sfEventDispatcher(), $options);
    }
}
