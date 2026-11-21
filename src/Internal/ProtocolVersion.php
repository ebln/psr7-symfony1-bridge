<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Internal;

/**
 * @internal
 */
final class ProtocolVersion
{
    private const DEFAULT_VERSION = '1.1';

    /**
     * @param mixed $value the SERVER_PROTOCOL value, for example "HTTP/1.1"
     */
    public static function fromServerProtocol($value): string
    {
        if (is_string($value) && 1 === preg_match('~^HTTP/(\d+(?:\.\d+)?)$~', $value, $matches)) {
            return $matches[1];
        }

        return self::DEFAULT_VERSION;
    }
}
