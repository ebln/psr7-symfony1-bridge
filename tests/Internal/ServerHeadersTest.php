<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Internal;

use ebln\Symfony1\Psr7Bridge\Internal\ServerHeaders;
use PHPUnit\Framework\TestCase;

final class ServerHeadersTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $server
     * @param array<string, string>   $expected
     *
     * @dataProvider provideServerValues
     */
    public function testMapsServerValues(array $server, array $expected): void
    {
        $actual = ServerHeaders::fromServer($server);
        ksort($actual);
        ksort($expected);

        self::assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{array<array-key, mixed>, array<string, string>}>
     */
    public static function provideServerValues(): array
    {
        return [
            'request/header-values-split-on-comma' => [
                ['HTTP_IF_MODIFIED_SINCE' => 'Wed, 21 Oct 2015 07:28:00 GMT', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko)'],
                ['if-modified-since' => 'Wed, 21 Oct 2015 07:28:00 GMT', 'user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko)'],
            ],
            'request/list-header-needs-one-value' => [
                ['HTTP_ACCEPT' => 'foo, bar'],
                ['accept' => 'foo, bar'],
            ],
            'request/content-headers-have-no-http-prefix' => [
                ['CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => '7', 'CONTENT_MD5' => 'abc'],
                ['content-type' => 'application/json', 'content-length' => '7', 'content-md5' => 'abc'],
            ],
            'request/content-type-both-keys-present' => [
                ['CONTENT_TYPE' => 'a/b', 'HTTP_CONTENT_TYPE' => 'c/d'],
                ['content-type' => 'a/b'],
            ],
            'content type, both keys, other array order' => [
                ['HTTP_CONTENT_TYPE' => 'c/d', 'CONTENT_TYPE' => 'a/b'],
                ['content-type' => 'a/b'],
            ],
            'request/http-content-type-without-content-type' => [
                ['HTTP_CONTENT_TYPE' => 'c/d'],
                ['content-type' => 'c/d'],
            ],
            'empty CONTENT_TYPE does not win' => [
                ['CONTENT_TYPE' => '', 'HTTP_CONTENT_TYPE' => 'c/d'],
                ['content-type' => 'c/d'],
            ],
            'content length zero' => [
                ['CONTENT_LENGTH' => '0'],
                ['content-length' => '0'],
            ],
            'headers/shadow-compare-ignores-stripslashes' => [
                ['HTTP_X_PATH' => 'C:\dir, b'],
                ['x-path' => 'C:\dir, b'],
            ],
            'headers/value-whitespace-trimming' => [
                ['HTTP_X_FOO' => " bar\t"],
                ['x-foo' => 'bar'],
            ],
            'request/header-name-case-and-underscore' => [
                ['HTTP_X_TEST' => 'a'],
                ['x-test' => 'a'],
            ],
            'response/non-string-header-value-typeerror' => [
                ['HTTP_X_COUNT' => 5],
                ['x-count' => '5'],
            ],
            'not scalar is skipped' => [
                ['HTTP_X_LIST' => ['a'], 'HTTP_X_NULL' => null],
                [],
            ],
            'authorization header present' => [
                ['HTTP_AUTHORIZATION' => 'Bearer t', 'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer r', 'PHP_AUTH_USER' => 'u'],
                ['authorization' => 'Bearer t'],
            ],
            'empty HTTP_AUTHORIZATION stays an empty header' => [
                ['HTTP_AUTHORIZATION' => ''],
                ['authorization' => ''],
            ],
            'empty HTTP_AUTHORIZATION is not the source' => [
                ['HTTP_AUTHORIZATION' => '', 'PHP_AUTH_DIGEST' => 'Digest x'],
                ['authorization' => 'Digest x'],
            ],
            'redirect fallback' => [
                ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer r', 'PHP_AUTH_USER' => 'u', 'PHP_AUTH_PW' => 'p'],
                ['authorization' => 'Bearer r'],
            ],
            'basic fallback' => [
                ['PHP_AUTH_USER' => 'u', 'PHP_AUTH_PW' => 'p', 'PHP_AUTH_DIGEST' => 'Digest x'],
                ['authorization' => 'Basic dTpw'],
            ],
            'basic without password' => [
                ['PHP_AUTH_USER' => 'u'],
                ['authorization' => 'Basic dTo='],
            ],
            'digest fallback' => [
                ['PHP_AUTH_DIGEST' => 'Digest x'],
                ['authorization' => 'Digest x'],
            ],
            'content type, empty CONTENT_TYPE, reverse order' => [
                ['HTTP_CONTENT_TYPE' => 'c/d', 'CONTENT_TYPE' => ''],
                ['content-type' => 'c/d'],
            ],
            'empty CONTENT_TYPE alone' => [
                ['CONTENT_TYPE' => ''],
                [],
            ],
            'empty CONTENT_LENGTH alone' => [
                ['CONTENT_LENGTH' => ''],
                [],
            ],
            'CONTENT_LENGTH wins over HTTP_CONTENT_LENGTH' => [
                ['CONTENT_LENGTH' => '5', 'HTTP_CONTENT_LENGTH' => '7'],
                ['content-length' => '5'],
            ],
            'CONTENT_LENGTH wins, reverse order' => [
                ['HTTP_CONTENT_LENGTH' => '7', 'CONTENT_LENGTH' => '5'],
                ['content-length' => '5'],
            ],
            'CONTENT_MD5 wins over HTTP_CONTENT_MD5' => [
                ['CONTENT_MD5' => 'a', 'HTTP_CONTENT_MD5' => 'b'],
                ['content-md5' => 'a'],
            ],
            'CONTENT_MD5 wins, reverse order' => [
                ['HTTP_CONTENT_MD5' => 'b', 'CONTENT_MD5' => 'a'],
                ['content-md5' => 'a'],
            ],
            'basic with empty user' => [
                ['PHP_AUTH_USER' => '', 'PHP_AUTH_PW' => 'token'],
                ['authorization' => 'Basic OnRva2Vu'],
            ],
            'basic credentials are not trimmed' => [
                ['PHP_AUTH_USER' => 'u', 'PHP_AUTH_PW' => 'p '],
                ['authorization' => 'Basic dTpwIA=='],
            ],
            'trim removes only spaces and tabs' => [
                ['HTTP_X_A' => "a\n", 'HTTP_X_B' => "b\r", 'HTTP_X_C' => " c \t"],
                ['x-a' => "a\n", 'x-b' => "b\r", 'x-c' => 'c'],
            ],
            'empty HTTP_ value stays empty' => [
                ['HTTP_X_FOO' => ''],
                ['x-foo' => ''],
            ],
            'bool values are cast' => [
                ['HTTP_X_ON' => true, 'HTTP_X_OFF' => false],
                ['x-on' => '1', 'x-off' => ''],
            ],
            'no headers' => [
                ['SERVER_NAME' => 'example.org', 'REQUEST_METHOD' => 'GET', 'HTTP_' => 'x', 0 => 'x'],
                [],
            ],
        ];
    }
}
