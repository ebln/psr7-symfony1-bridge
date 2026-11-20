<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Internal;

use ebln\Symfony1\Psr7Bridge\ConversionFailed;
use ebln\Symfony1\Psr7Bridge\Internal\SetCookie;
use ebln\Symfony1\Psr7Bridge\Internal\SetCookieParser;
use PHPUnit\Framework\TestCase;

final class SetCookieParserTest extends TestCase
{
    private const NOW = 1767225600; // 2026-01-01 00:00:00 UTC

    /**
     * @param array<string, mixed> $expected the properties that differ from the defaults
     *
     * @dataProvider provideHeaders
     */
    public function testParses(string $header, array $expected): void
    {
        $expected += [
            'name' => 'a',
            'value' => 'b',
            'expire' => null,
            'path' => null,
            'domain' => '',
            'secure' => false,
            'httpOnly' => false,
            'sameSite' => '',
        ];

        $cookie = SetCookieParser::parse($header, static function (): int {
            return self::NOW;
        });

        $actual = get_object_vars($cookie);
        ksort($expected);
        ksort($actual);
        self::assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function provideHeaders(): array
    {
        $date = 'Wed, 21 Oct 2015 07:28:00 GMT';

        return [
            'name and value' => ['a=b', []],
            'all attributes (cookies/samesite-dropped)' => [
                'sid=abc; Expires='.$date.'; Path=/app; Domain=example.org; Secure; HttpOnly; SameSite=Strict',
                [
                    'name' => 'sid',
                    'value' => 'abc',
                    'expire' => 1445412480,
                    'path' => '/app',
                    'domain' => 'example.org',
                    'secure' => true,
                    'httpOnly' => true,
                    'sameSite' => 'Strict',
                ],
            ],
            'attribute names without case' => [
                'a=b; PATH=/x; secure; httponly; samesite=Lax',
                ['path' => '/x', 'secure' => true, 'httpOnly' => true, 'sameSite' => 'Lax'],
            ],
            'no equals sign' => ['flag', ['name' => 'flag', 'value' => '']],
            'equals sign in the value' => ['a=b=c', ['value' => 'b=c']],
            'value is not decoded' => ['a=%20x', ['value' => '%20x']],
            'white space is trimmed' => [' a = b ; Path = /x ', ['path' => '/x']],
            'unknown attribute' => ['a=b; Priority=High', []],
            'unparsable Expires' => ['a=b; Expires=not a date', ['expire' => null]],
            'empty Expires' => ['a=b; Expires=', ['expire' => null]],
            'Expires with dashes' => ['a=b; Expires=Wed, 21-Oct-2015 07:28:00 GMT', ['expire' => 1445412480]],
            'empty Path' => ['a=b; Path=', ['path' => null]],
            'Max-Age' => ['a=b; Max-Age=60', ['expire' => 1767225660]],
            'Max-Age wins, Expires first (cookies/max-age-versus-expires-precedence)' => [
                'a=b; Expires='.$date.'; Max-Age=60',
                ['expire' => 1767225660],
            ],
            'Max-Age wins, Max-Age first' => ['a=b; Max-Age=60; Expires='.$date, ['expire' => 1767225660]],
            'invalid Max-Age leaves Expires' => ['a=b; Max-Age=abc; Expires='.$date, ['expire' => 1445412480]],
            'Max-Age 0' => ['a=b; Max-Age=0', ['expire' => 1]],
            'negative Max-Age' => ['a=b; Max-Age=-5', ['expire' => 1]],
            'Expires at the epoch' => ['a=b; Expires=Thu, 01 Jan 1970 00:00:00 GMT', ['expire' => 1]],
            'Expires before the epoch' => ['a=b; Expires=Wed, 31 Dec 1969 23:00:00 GMT', ['expire' => 1]],
            'repeated attribute, the last one wins' => ['a=b; Path=/x; Path=/y', ['path' => '/y']],
            'repeated valid Max-Age, the last one wins' => ['a=b; Max-Age=60; Max-Age=120', ['expire' => 1767225720]],
            'invalid repeated Max-Age is ignored' => ['a=b; Max-Age=60; Max-Age=abc', ['expire' => 1767225660]],
            'invalid repeated Max-Age leaves a valid Max-Age before Expires' => [
                'a=b; Max-Age=60; Max-Age=abc; Expires='.$date,
                ['expire' => 1767225660],
            ],
            'invalid repeated Expires is ignored' => [
                'a=b; Expires='.$date.'; Expires=garbage',
                ['expire' => 1445412480],
            ],
            'Max-Age beyond year 9999' => ['a=b; Max-Age=999999999999999', ['expire' => 253402300799]],
            'Max-Age beyond the integer range' => [
                'a=b; Max-Age=999999999999999999999999999999',
                ['expire' => 253402300799],
            ],
            'Expires beyond year 9999' => ['a=b; Expires=Fri, 31 Dec 9999 23:59:59 -0100', ['expire' => 253402300799]],
            'Secure only' => ['a=b; Secure', ['secure' => true]],
            'HttpOnly only' => ['a=b; HttpOnly', ['httpOnly' => true]],
            'empty parts are ignored' => ['a=b;; ; Path=/x;', ['path' => '/x']],
            'Max-Age with a sign is invalid' => ['a=b; Max-Age=+5', []],
        ];
    }

    public function testExpiresWithoutZoneDoesNotDependOnTheDefaultTimeZone(): void
    {
        $before = date_default_timezone_get();
        $results = [];
        try {
            foreach (['UTC', 'Pacific/Auckland', 'America/Los_Angeles'] as $zone) {
                date_default_timezone_set($zone);
                foreach (['Wed, 21 Oct 2015 07:28:00', 'Wed Oct 21 07:28:00 2015'] as $date) {
                    $results[] = SetCookieParser::parse('a=b; Expires='.$date, static function (): int {
                        return self::NOW;
                    })->expire;
                }
            }
        } finally {
            date_default_timezone_set($before);
        }

        self::assertSame(array_fill(0, 6, 1445412480), $results);
    }

    /**
     * @dataProvider provideClockUse
     */
    public function testClockIsCalledOnlyForMaxAge(string $header, int $calls): void
    {
        $count = 0;
        SetCookieParser::parse($header, static function () use (&$count): int {
            ++$count;

            return self::NOW;
        });

        self::assertSame($calls, $count);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function provideClockUse(): array
    {
        return [
            'Expires' => ['a=b; Expires=Wed, 21 Oct 2015 07:28:00 GMT', 0],
            'invalid Max-Age' => ['a=b; Max-Age=abc', 0],
            'Max-Age' => ['a=b; Max-Age=60', 1],
        ];
    }

    /**
     * @dataProvider provideHeadersWithoutName
     */
    public function testMissingNameFails(string $header): void
    {
        $this->expectException(ConversionFailed::class);

        SetCookieParser::parse($header, static function (): int {
            return self::NOW;
        });
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideHeadersWithoutName(): array
    {
        return [
            'empty name' => ['=b'],
            'empty header' => [''],
            'only attributes' => ['; Path=/'],
        ];
    }

    public function testConstructorOrder(): void
    {
        $cookie = new SetCookie('n', 'v', 5, '/p', 'd', true, false, 'None');

        self::assertSame(['n', 'v', 5, '/p', 'd', true, false, 'None'], array_values(get_object_vars($cookie)));
    }
}
