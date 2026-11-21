<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Internal;

use ebln\Symfony1\Psr7Bridge\ConversionFailed;
use ebln\Symfony1\Psr7Bridge\Internal\SetCookie;
use ebln\Symfony1\Psr7Bridge\Internal\SetCookieParser;
use ebln\Symfony1\Psr7Bridge\Internal\SetCookieSerializer;
use ebln\Symfony1\Psr7Bridge\Tests\Support\SymfonyObjects;
use PHPUnit\Framework\TestCase;

final class SetCookieSerializerTest extends TestCase
{
    private const DATE = 'Wed, 21 Oct 2015 07:28:00 GMT';

    private const ALL_FIELDS = [
        'name' => 'sid',
        'value' => 'abc',
        'expire' => 1445412480,
        'path' => '/app',
        'domain' => 'example.org',
        'secure' => true,
        'httpOnly' => true,
        'samesite' => 'Strict',
    ];

    private const ALL_FIELDS_HEADER = 'sid=abc; Expires='.self::DATE
        .'; Path=/app; Domain=example.org; Secure; HttpOnly; SameSite=Strict';

    private string $timeZone;

    protected function setUp(): void
    {
        $this->timeZone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timeZone);
    }

    public function testAllFields(): void
    {
        self::assertSame(self::ALL_FIELDS_HEADER, SetCookieSerializer::serialize(self::ALL_FIELDS));
    }

    public function testSessionCookie(): void
    {
        self::assertSame('a=b; Path=/', SetCookieSerializer::serialize($this->session(['expire' => null])));
    }

    public function testExpireZeroIsASessionCookie(): void
    {
        self::assertSame('a=b; Path=/', SetCookieSerializer::serialize($this->session(['expire' => 0])));
    }

    public function testStringZeroExpireIsASessionCookie(): void
    {
        self::assertSame('a=b; Path=/', SetCookieSerializer::serialize($this->session(['expire' => '0'])));
    }

    public function testEmptyStringExpireIsASessionCookie(): void
    {
        self::assertSame('a=b; Path=/', SetCookieSerializer::serialize($this->session(['expire' => ''])));
    }

    public function testNullPathDomainAndSamesiteAreLeftOut(): void
    {
        $cookie = ['name' => 'a', 'value' => 'b', 'expire' => null, 'path' => null, 'domain' => null, 'samesite' => null];

        self::assertSame('a=b', SetCookieSerializer::serialize($cookie));
    }

    public function testMissingKeysUseDefaults(): void
    {
        self::assertSame('a=b', SetCookieSerializer::serialize(['name' => 'a', 'value' => 'b']));
    }

    public function testNumericStringExpire(): void
    {
        $header = SetCookieSerializer::serialize(['name' => 'a', 'value' => 'b', 'expire' => '1445412480']);

        self::assertStringContainsString('Expires='.self::DATE, $header);
    }

    public function testExpiresIsGmtInAnyTimeZone(): void
    {
        date_default_timezone_set('Europe/Berlin');

        self::assertSame(self::ALL_FIELDS_HEADER, SetCookieSerializer::serialize(self::ALL_FIELDS));
    }

    public function testCookieStoredBySymfony(): void
    {
        $response = SymfonyObjects::response();
        // The docblock of setCookie() names string|null for $expire; null is the default. Symfony stores a
        // numeric string as an integer.
        $response->setCookie('n', 'v', '1445412480', '/', '', true, true, 'Lax');
        self::assertSame(1445412480, self::firstCookie($response)['expire']);

        self::assertSame(
            'n=v; Expires='.self::DATE.'; Path=/; Secure; HttpOnly; SameSite=Lax',
            SetCookieSerializer::serialize(self::firstCookie($response))
        );
    }

    public function testCookieStoredBySymfonyWithDefaults(): void
    {
        $response = SymfonyObjects::response();
        $response->setCookie('n', 'v');
        $cookie = self::firstCookie($response);

        self::assertSame(
            ['name' => 'n', 'value' => 'v', 'expire' => null, 'path' => '/', 'domain' => '', 'secure' => false, 'httpOnly' => false, 'samesite' => ''],
            $cookie
        );
        self::assertSame('n=v; Path=/', SetCookieSerializer::serialize($cookie));
    }

    public function testDeletionExpiryIsWrittenAsStored(): void
    {
        $header = SetCookieSerializer::serialize(['name' => 'a', 'value' => '', 'expire' => 1]);

        self::assertSame('a=; Expires=Thu, 01 Jan 1970 00:00:01 GMT', $header);
    }

    public function testNegativeExpiryIsWrittenAsStored(): void
    {
        $header = SetCookieSerializer::serialize(['name' => 'a', 'value' => 'b', 'expire' => -5]);

        self::assertSame('a=b; Expires=Wed, 31 Dec 1969 23:59:55 GMT', $header);
    }

    public function testOnlyTheKeySamesiteIsRead(): void
    {
        self::assertSame('a=b', SetCookieSerializer::serialize(['name' => 'a', 'value' => 'b', 'sameSite' => 'Lax']));
    }

    public function testParserReadsTheResultBack(): void
    {
        $cookie = SetCookieParser::parse(SetCookieSerializer::serialize(self::ALL_FIELDS), static function (): int {
            return 0;
        });

        self::assertEquals(
            new SetCookie('sid', 'abc', 1445412480, '/app', 'example.org', true, true, 'Strict'),
            $cookie
        );
    }

    /**
     * @param array<string, mixed> $cookie
     *
     * @dataProvider provideCookiesWithoutName
     */
    public function testMissingNameFails(array $cookie): void
    {
        $this->expectException(ConversionFailed::class);

        SetCookieSerializer::serialize($cookie);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function provideCookiesWithoutName(): array
    {
        return [
            'no key' => [['value' => 'b']],
            'empty name' => [['name' => '', 'value' => 'b']],
            'null name' => [['name' => null, 'value' => 'b']],
            'array name' => [['name' => ['a'], 'value' => 'b']],
        ];
    }

    /**
     * @param array<string, mixed> $cookie
     *
     * @dataProvider provideCookiesWithSeparators
     */
    public function testSeparatorCharactersFail(array $cookie, string $message): void
    {
        $this->expectException(ConversionFailed::class);
        $this->expectExceptionMessage($message);

        SetCookieSerializer::serialize($cookie);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function provideCookiesWithSeparators(): array
    {
        $value = 'The cookie "a" cannot be written as Set-Cookie: its value holds a separator character';
        $name = 'A cookie cannot be written as Set-Cookie: its name holds a separator character';

        return [
            'semicolon in value' => [['name' => 'a', 'value' => 'x; Domain=example.org; Secure'], $value],
            'comma in value' => [['name' => 'a', 'value' => 'x,y'], $value],
            'space in value' => [['name' => 'a', 'value' => 'x y'], $value],
            'tab in value' => [['name' => 'a', 'value' => "x\ty"], $value],
            'CR in value' => [['name' => 'a', 'value' => "x\ry"], $value],
            'LF in value' => [['name' => 'a', 'value' => "x\ny"], $value],
            'vertical tab in value' => [['name' => 'a', 'value' => "x\x0By"], $value],
            'form feed in value' => [['name' => 'a', 'value' => "x\x0Cy"], $value],
            'equals sign in name' => [['name' => 'a=b', 'value' => 'c'], $name],
            'space in name' => [['name' => 'a b', 'value' => 'c'], $name],
            'semicolon in path' => [
                ['name' => 'a', 'value' => 'b', 'path' => '/; Domain=example.org'],
                'The cookie "a" cannot be written as Set-Cookie: its path holds a separator character',
            ],
            'semicolon in domain' => [
                ['name' => 'a', 'value' => 'b', 'domain' => 'example.org; Secure'],
                'The cookie "a" cannot be written as Set-Cookie: its domain holds a separator character',
            ],
            'semicolon in samesite' => [
                ['name' => 'a', 'value' => 'b', 'samesite' => 'Lax; Secure'],
                'The cookie "a" cannot be written as Set-Cookie: its samesite holds a separator character',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $cookie
     *
     * @dataProvider provideCookiesWithAllowedCharacters
     */
    public function testAllowedCharactersAreWritten(array $cookie, string $expected): void
    {
        self::assertSame($expected, SetCookieSerializer::serialize($cookie));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function provideCookiesWithAllowedCharacters(): array
    {
        return [
            'equals sign in value' => [['name' => 'a', 'value' => 'b=c'], 'a=b=c'],
            'percent encoded space in value' => [['name' => 'a', 'value' => '%20x'], 'a=%20x'],
            'path with slashes' => [['name' => 'a', 'value' => 'b', 'path' => '/a/b'], 'a=b; Path=/a/b'],
            'domain with leading dot' => [
                ['name' => 'a', 'value' => 'b', 'domain' => '.example.org'],
                'a=b; Domain=.example.org',
            ],
        ];
    }

    public function testNonScalarValueIsWrittenAsEmpty(): void
    {
        self::assertSame('a=', SetCookieSerializer::serialize(['name' => 'a', 'value' => ['x']]));
    }

    public function testNullValueIsWrittenAsEmpty(): void
    {
        self::assertSame('a=', SetCookieSerializer::serialize(['name' => 'a', 'value' => null]));
    }

    public function testScalarsAreWrittenAsStrings(): void
    {
        $cookie = ['name' => 12, 'value' => 3.5, 'path' => 7];

        self::assertSame('12=3.5; Path=7', SetCookieSerializer::serialize($cookie));
    }

    public function testNonScalarAttributesAreLeftOut(): void
    {
        $cookie = [
            'name' => 'a',
            'value' => 'b',
            'expire' => ['x'],
            'path' => ['x'],
            'domain' => new \stdClass(),
            'secure' => ['x'],
            'httpOnly' => new \stdClass(),
            'samesite' => ['x'],
        ];

        // PHP reads the flags of setrawcookie() by truthiness: a non-empty array and an object are true.
        self::assertSame('a=b; Secure; HttpOnly', SetCookieSerializer::serialize($cookie));
    }

    public function testEmptyArrayFlagsAreNotSet(): void
    {
        $cookie = ['name' => 'a', 'value' => 'b', 'secure' => [], 'httpOnly' => []];

        self::assertSame('a=b', SetCookieSerializer::serialize($cookie));
    }

    public function testStringableObjectsAreWrittenAsTheirText(): void
    {
        $cookie = [
            'name' => self::stringable('a'),
            'value' => self::stringable('b'),
            'path' => self::stringable('/p'),
            'domain' => self::stringable('example.org'),
            'samesite' => self::stringable('Lax'),
        ];

        self::assertSame('a=b; Path=/p; Domain=example.org; SameSite=Lax', SetCookieSerializer::serialize($cookie));
    }

    public function testSeparatorInStringableObjectFails(): void
    {
        $this->expectException(ConversionFailed::class);

        SetCookieSerializer::serialize(['name' => 'a', 'value' => self::stringable('x; Secure')]);
    }

    public function testNonNumericExpireIsLeftOut(): void
    {
        self::assertSame('a=b', SetCookieSerializer::serialize(['name' => 'a', 'value' => 'b', 'expire' => 'soon']));
    }

    public function testTruthyFlagsAreSet(): void
    {
        // symfony stores httpOnly as passed, so 1 is possible
        $header = SetCookieSerializer::serialize(['name' => 'a', 'value' => 'b', 'secure' => 1, 'httpOnly' => 1]);

        self::assertSame('a=b; Secure; HttpOnly', $header);
    }

    private static function stringable(string $text): object
    {
        return new class($text) {
            private string $text;

            public function __construct(string $text)
            {
                $this->text = $text;
            }

            public function __toString(): string
            {
                return $this->text;
            }
        };
    }

    /**
     * @return array<mixed>
     */
    private static function firstCookie(\sfWebResponse $response): array
    {
        $cookies = array_values($response->getCookies());
        $cookie = $cookies[0] ?? null;
        if (!is_array($cookie)) {
            self::fail('The response holds no cookie');
        }

        return $cookie;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function session(array $overrides): array
    {
        return $overrides + [
            'name' => 'a',
            'value' => 'b',
            'expire' => null,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httpOnly' => false,
            'samesite' => '',
        ];
    }
}
