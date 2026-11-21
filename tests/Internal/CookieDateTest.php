<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Internal;

use ebln\Symfony1\Psr7Bridge\Internal\CookieDate;
use PHPUnit\Framework\TestCase;

final class CookieDateTest extends TestCase
{
    /**
     * @dataProvider provideValues
     */
    public function testParse(string $value, ?int $expected): void
    {
        self::assertSame($expected, CookieDate::parse($value));
    }

    /**
     * @return iterable<string, array{string, int|null}>
     */
    public function provideValues(): iterable
    {
        yield 'RFC 1123 format' => ['Sun, 06 Nov 1994 08:49:37 GMT', 784111777];
        yield 'RFC 850 format' => ['Sunday, 06-Nov-94 08:49:37 GMT', 784111777];
        yield 'asctime format' => ['Sun Nov  6 08:49:37 1994', 784111777];
        yield 'fields in another order' => ['2015 Oct 21 07:28:00', 1445412480];
        yield 'time parts of one digit' => ['Wed, 21 Oct 2015 7:8:9 GMT', 1445411289];
        yield 'only three letters of the month count' => ['Wed, 21 Octember 2015 07:28:00 GMT', 1445412480];
        yield 'text after the time' => ['21 Oct 2015 07:28:00abc', 1445412480];
        yield 'text after the day' => ['21abc Oct 2015 07:28:00', 1445412480];
        yield 'numeric zone after the year is ignored' => ['Wed, 21 Oct 2015 07:28:00 +0200', 1445412480];
        yield 'zone name is ignored' => ['Wed, 21 Oct 2015 07:28:00 PST', 1445412480];
        yield 'zone fills the missing year' => ['Wed, 21 Oct 08:28:00 +0000', 972116880];
        yield 'wrong weekday is ignored' => ['Mon, 21 Oct 2015 07:28:00 GMT', 1445412480];
        yield 'wrong weekday is ignored, other weekday' => ['Thu, 21 Oct 2015 07:28:00 GMT', 1445412480];
        yield 'relative word before the date (date-create-applies-words-browsers-ignore)' => ['tomorrow 10 Jan 2030 10:00:00', 1894269600];
        yield 'relative words after the date' => ['10 Jan 2030 10:00:00 +5 years', 1894269600];
        yield 'number after the plus sign is the day' => ['Jan 2030 10:00:00 +10 days', 1894269600];
        yield 'relative words after the year' => ['Oct 21 07:28:00 2015 +1 weekday', 1445412480];
        yield 'dash between year and time (php8-reads-time-after-dash-as-offset)' => ['Wed, 21 Oct 2015-07:28:00 GMT', 1445412480];
        yield 'number before the year is the year (misread-year-matches-later-token)' => ['00:00:00-1969 Dec  01 +01:00', -2678400];
        yield 'three-digit year below 1601 with a later number' => ['Wed, 21 Oct 201 07:28:00 +0000 +0201', null];
        yield 'one-digit number is no field' => ['Wed, 21 Oct 5 07:28:00 +0000 05', 972113280];
        yield 'one-digit number is no field, zone name' => ['Wed, 21 Oct 5 07:28:00 GMT 05', 1129879680];
        yield 'year 070 is the number 70, which gives 1970' => ['Wed, 21 Oct 070 07:28:00 +0000 +1970', 25342080];
        yield 'a second time is ignored' => ['Wed, 21 Oct 2015 07:28:00 08:00:00 GMT', 1445412480];
        yield 'a second month is ignored' => ['Wed, 21 Oct Nov 2015 07:28:00 GMT', 1445412480];
        yield 'year 99 is 1999' => ['Wed, 21 Oct 99 07:28:00 GMT', 940490880];
        yield 'year 0015 is 2015' => ['Wed, 21 Oct 0015 07:28:00 GMT', 1445412480];
        yield 'year 1600 is no date (year-before-1601-accepted)' => ['Wed, 21 Oct 1600 07:28:00 GMT', null];
        yield 'year 1000 is no date' => ['Wed, 21 Oct 1000 07:28:00 GMT', null];
        yield 'year 1015 is no date' => ['Wed Oct 21 07:28:00 1015', null];
        yield 'year 0728 is no date' => ['Wed Oct 21 07:28:00 0728', null];
        yield 'year 0100 after a zone name is no date' => ['Wed Oct 21 07:28:00 CEST 0100', null];
        yield 'the dash is a delimiter and the year is 999, which is no date' => ['Thu-7 -0999  Oct 01 00:00:00', null];
        yield 'lowest year' => ['Wed, 21 Oct 1601 07:28:00 GMT', -11619131520];
        yield 'epoch' => ['Thu, 01 Jan 1970 00:00:00 GMT', 0];
        yield 'one hour before the epoch' => ['Wed, 31 Dec 1969 23:00:00 GMT', -3600];
        yield 'largest result' => ['Fri, 31 Dec 9999 23:59:59 GMT', 253402300799];
        yield 'leap day of 2000' => ['Tue, 29 Feb 2000 07:28:00 GMT', 951809280];
        yield 'leap day of 2016' => ['Mon, 29 Feb 2016 07:28:00 GMT', 1456730880];
        yield 'no leap day in 1900' => ['Thu, 29 Feb 1900 07:28:00 GMT', null];
        yield 'no leap day in 2015' => ['Sun, 29 Feb 2015 07:28:00 GMT', null];
        yield '31 February' => ['Tue, 31 Feb 2015 07:28:00 GMT', null];
        yield '31 April' => ['Fri, 31 Apr 2015 07:28:00 GMT', null];
        yield 'day below 1' => ['Wed, 0 Oct 2015 07:28:00 GMT', null];
        yield 'day above 31' => ['Wed, 32 Oct 2015 07:28:00 GMT', null];
        yield 'hour above 23' => ['Wed, 21 Oct 2015 24:00:00 GMT', null];
        yield 'minute above 59' => ['Wed, 21 Oct 2015 23:60:00 GMT', null];
        yield 'second above 59' => ['Wed, 21 Oct 2015 23:59:60 GMT', null];
        yield 'five digits are no year' => ['Wed, 21 Oct 20155 07:28:00 GMT', null];
        yield 'three digits are no second' => ['Wed, 21 Oct 2015 07:28:001 GMT', null];
        yield 'time needs seconds' => ['Wed, 21 Oct 2015 07:28 GMT', null];
        yield 'no time' => ['10 Jan 2030', null];
        yield 'no day, no year' => ['Jan 10:00:00', null];
        yield 'no day' => ['Jan 2030 10:00:00', null];
        yield 'no month' => ['21 2015 07:28:00 GMT', null];
        yield 'text before the time is no time' => ['Wed, 21 Oct 2015 x07:28:00 GMT', null];
        yield 'text before the year is no year' => ['Wed, 21 Oct x2015 07:28:00 GMT', null];
        yield 'empty string' => ['', null];
        yield 'bytes above ASCII after the date' => ["Wed, 21 Oct 2015 07:28:00 \xFF\xFE", 1445412480];
        yield 'invalid UTF-8 only' => ["\xC3\x28", null];
    }
}
