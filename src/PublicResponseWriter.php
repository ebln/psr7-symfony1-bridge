<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge;

use ebln\Symfony1\Psr7Bridge\Internal\CookieSeparators;
use ebln\Symfony1\Psr7Bridge\Internal\SetCookie;
use ebln\Symfony1\Psr7Bridge\Internal\SetCookieParser;
use Psr\Http\Message\ResponseInterface;

/**
 * Writes a PSR-7 response into a symfony1 web response through its public methods.
 *
 * The writer writes on top of the existing state of the target. It clears no header and no cookie.
 * Nothing is written to the target unless every `Set-Cookie` value is parsed, the time source has
 * answered where a cookie needs it, and the body is read.
 */
final class PublicResponseWriter implements ResponseWriter
{
    private \Closure $now;

    /**
     * @param (callable(): \DateTimeInterface)|null $now source of the current time, used for `Max-Age`
     */
    public function __construct(?callable $now = null)
    {
        $this->now = null === $now
            ? static function (): \DateTimeImmutable {
                return new \DateTimeImmutable();
            }
        : \Closure::fromCallable($now);
    }

    /**
     * @throws ConversionFailed
     */
    public function write(ResponseInterface $source, \sfWebResponse $target): void
    {
        // Everything that can fail happens before the first write to the target.
        try {
            $cookies = $this->parseCookies($source);
            $headers = $this->joinHeaders($source);
            $body = $this->readBody($source);
            $code = $source->getStatusCode();
            $phrase = $source->getReasonPhrase();
        } catch (ConversionFailed $e) {
            throw $e;
        } catch (\Exception $e) {
            throw ConversionFailed::because('The PSR-7 response cannot be read', $e);
        }

        $target->setStatusCode($code, $phrase);
        if (204 === $code) {
            $target->setHeaderOnly(true);
        }
        foreach ($headers as $name => $value) {
            $target->setHttpHeader((string) $name, $value);
        }
        foreach ($cookies as $cookie) {
            $target->setCookie(
                $cookie->name,
                $cookie->value,
                $cookie->expire,
                $cookie->path ?? '',
                $cookie->domain,
                $cookie->secure,
                $cookie->httpOnly,
                $cookie->sameSite
            );
        }
        $target->setContent($body);
    }

    /**
     * @return list<SetCookie>
     *
     * @throws ConversionFailed
     */
    private function parseCookies(ResponseInterface $source): array
    {
        $time = null;
        $now = function () use (&$time): int {
            if (null === $time) {
                try {
                    $current = ($this->now)();
                } catch (\Exception $e) {
                    throw ConversionFailed::because('The time source failed', $e);
                }
                if (!$current instanceof \DateTimeInterface) {
                    throw ConversionFailed::because('The time source must return a DateTimeInterface');
                }
                $time = $current->getTimestamp();
            }

            return $time;
        };

        $cookies = [];
        foreach ($source->getHeader('Set-Cookie') as $value) {
            $cookie = SetCookieParser::parse($value, $now);
            self::assertSendable($cookie);
            $cookies[] = $cookie;
        }

        return $cookies;
    }

    /**
     * PHP refuses a separator character in the name, the value, the path and the domain of a cookie when symfony sends
     * the cookie, after the status line and the headers have gone out. PHP sends a samesite value unchanged, so the
     * writer refuses a separator there as well, because it would add other attributes. The message never holds the
     * offending text; it holds the name only when the name is clean.
     *
     * @throws ConversionFailed
     */
    private static function assertSendable(SetCookie $cookie): void
    {
        if (CookieSeparators::appearIn($cookie->name)) {
            throw ConversionFailed::because('A Set-Cookie value cannot be sent by PHP: its cookie name holds a separator character');
        }
        $fields = ['value' => $cookie->value, 'path' => $cookie->path ?? '', 'domain' => $cookie->domain, 'samesite' => $cookie->sameSite];
        foreach ($fields as $field => $text) {
            if (CookieSeparators::appearIn($text)) {
                throw ConversionFailed::because(sprintf('The cookie "%s" cannot be sent by PHP: its %s holds a separator character', $cookie->name, $field));
            }
        }
    }

    /**
     * @return array<array-key, string> the values of each name joined with `, `, without `Set-Cookie`; PHP turns
     *                                  an all-digit name into an integer key, so the caller casts the key
     */
    private function joinHeaders(ResponseInterface $source): array
    {
        $joined = [];
        foreach ($source->getHeaders() as $name => $values) {
            // A name of digits only is an integer key in a PHP array; strtolower() needs a string.
            // sfWebResponse::normalizeHeaderName() turns `_` and a space into `-`: `Set_Cookie` is stored as
            // `Set-Cookie`, which would pass the cookie checks.
            if ('set-cookie' !== strtr(strtolower((string) $name), ['_' => '-', ' ' => '-'])) {
                $joined[$name] = implode(', ', $values);
            }
        }

        return $joined;
    }

    /**
     * @throws ConversionFailed
     */
    private function readBody(ResponseInterface $source): string
    {
        try {
            return (string) $source->getBody();
        } catch (\Exception $e) {
            throw ConversionFailed::because('The body of the response cannot be read', $e);
        }
    }
}
