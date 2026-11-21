<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Support;

use ebln\Symfony1\Psr7Bridge\Internal\SetCookieParser;
use Psr\Http\Message\ResponseInterface;

/**
 * The parts of a PSR-7 response that a round trip must keep, in a form that compares with `===`.
 *
 * Header names are lower case and sorted. `Set-Cookie` is not a header here; each cookie is parsed
 * and keyed by its name, with a missing path shown as `/`.
 */
final class ResponseSnapshot
{
    private const FIXED_TIME = 1767225600;

    /**
     * @return array{
     *     status: int,
     *     reason: string,
     *     protocol: string,
     *     headers: array<string, list<string>>,
     *     cookies: array<string, array{string, string, int|null, string, string, bool, bool, string}>,
     *     body: string
     * }
     */
    public static function of(ResponseInterface $response): array
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $name = strtolower((string) $name);
            if ('set-cookie' !== $name) {
                $headers[$name] = array_values($values);
            }
        }
        ksort($headers);

        $cookies = [];
        foreach ($response->getHeader('Set-Cookie') as $line) {
            $cookie = SetCookieParser::parse($line, static function (): int {
                return self::FIXED_TIME;
            });
            $cookies[$cookie->name] = [
                $cookie->name,
                $cookie->value,
                $cookie->expire,
                $cookie->path ?? '/',
                $cookie->domain,
                $cookie->secure,
                $cookie->httpOnly,
                $cookie->sameSite,
            ];
        }

        $body = $response->getBody();
        $body->rewind();

        return [
            'status' => $response->getStatusCode(),
            'reason' => $response->getReasonPhrase(),
            'protocol' => $response->getProtocolVersion(),
            'headers' => $headers,
            'cookies' => $cookies,
            'body' => $body->getContents(),
        ];
    }
}
