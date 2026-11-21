<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Internal;

/**
 * Reads the value of an `Expires` attribute as a browser does: the cookie-date algorithm of RFC 6265 section 5.1.1,
 * with erratum 4148. The parts of the value are read from left to right, and each part fills the first of the four
 * fields (time, day, month, year) that it fits and that is still empty. A part that fits no empty field is ignored.
 * The grammar of the year is `2*4DIGIT`, a year of two, three or four digits. The day, the year and the time may be
 * followed by a byte that is no digit and any bytes after it (`[ non-digit *OCTET ]`). The month is its first three
 * letters, followed by any bytes.
 *
 * The month is compared without case for ASCII letters. The grammar is ABNF (RFC 6265 section 2.2), and RFC 5234
 * section 2.3 makes a quoted string such as "jan" case-insensitive.
 *
 * @internal
 */
final class CookieDate
{
    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    /**
     * @return int|null the Unix time of the date in UTC (it can be negative; the lowest year is 1601, the largest
     *                  result is 253402300799), or null when the value is not a date
     */
    public static function parse(string $value): ?int
    {
        // No `u` modifier: a byte from 0x80 belongs to a token, and invalid UTF-8 is no error.
        $tokens = preg_split('/[\x09\x20-\x2F\x3B-\x40\x5B-\x60\x7B-\x7E]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (false === $tokens) {
            return null;
        }

        $time = null;
        $day = null;
        $month = null;
        $year = null;
        foreach ($tokens as $token) {
            if (null === $time && 1 === preg_match('/^(\d{1,2}):(\d{1,2}):(\d{1,2})(?!\d)/', $token, $found)) {
                $time = [
                    (int) $found[1],
                    (int) $found[2],
                    (int) $found[3],
                ];
            } elseif (null === $day && 1 === preg_match('/^(\d{1,2})(?!\d)/', $token, $found)) {
                $day = (int) $found[1];
            } elseif (null === $month && isset(self::MONTHS[strtolower(substr($token, 0, 3))])) {
                $month = self::MONTHS[strtolower(substr($token, 0, 3))];
            } elseif (null === $year && 1 === preg_match('/^(\d{2,4})(?!\d)/', $token, $found)) {
                $year = (int) $found[1];
            }
        }

        if (null === $time || null === $day || null === $month || null === $year) {
            return null;
        }
        if ($year >= 70 && $year <= 99) {
            $year += 1900;
        } elseif ($year <= 69) {
            $year += 2000;
        }
        if ($day < 1 || $day > 31 || $year < 1601 || $time[0] > 23 || $time[1] > 59 || $time[2] > 59) {
            return null;
        }
        if (!checkdate($month, $day, $year)) {
            return null;
        }
        $timestamp = gmmktime($time[0], $time[1], $time[2], $month, $day, $year);

        return false === $timestamp ? null : $timestamp;
    }
}
