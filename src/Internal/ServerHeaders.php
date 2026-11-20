<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Internal;

/**
 * @internal
 */
final class ServerHeaders
{
    private const CONTENT_KEYS = ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'];

    /**
     * Maps the entries of a server array to request headers. A value is never split at commas.
     *
     * @param array<array-key, mixed> $server
     *
     * @return array<array-key, string> lower-case header name to one value; PHP turns a name of digits only into an integer key
     */
    public static function fromServer(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $string = self::scalarToString($value);
            if (null === $string) {
                continue;
            }
            if ('HTTP_' === substr($key, 0, 5) && 'HTTP_' !== $key) {
                $headers[self::headerName(substr($key, 5))] = $string;
            }
        }

        // A non-empty CONTENT_* entry wins over the HTTP_CONTENT_* entry.
        foreach (self::CONTENT_KEYS as $key) {
            $value = self::nonEmpty($server, $key);
            if (null !== $value) {
                $headers[self::headerName($key)] = $value;
            }
        }

        $authorization = self::authorization($server);
        if (null !== $authorization) {
            $headers['authorization'] = $authorization;
        }

        return $headers;
    }

    /**
     * @param array<array-key, mixed> $server
     */
    private static function authorization(array $server): ?string
    {
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            $value = self::nonEmpty($server, $key);
            if (null !== $value) {
                return $value;
            }
        }

        $user = $server['PHP_AUTH_USER'] ?? null;
        if (is_scalar($user)) {
            $password = $server['PHP_AUTH_PW'] ?? null;

            // Credentials are encoded as they are, without trimming.
            return 'Basic '.base64_encode((string) $user.':'.(is_scalar($password) ? (string) $password : ''));
        }

        return self::nonEmpty($server, 'PHP_AUTH_DIGEST');
    }

    /**
     * @param array<array-key, mixed> $server
     */
    private static function nonEmpty(array $server, string $key): ?string
    {
        $value = self::scalarToString($server[$key] ?? null);

        return '' === $value ? null : $value;
    }

    /**
     * @param mixed $value a server array entry
     *
     * @return string|null the trimmed string, or null when the value is not scalar
     */
    private static function scalarToString($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        return trim((string) $value, " \t");
    }

    private static function headerName(string $serverName): string
    {
        return strtolower(str_replace('_', '-', $serverName));
    }
}
