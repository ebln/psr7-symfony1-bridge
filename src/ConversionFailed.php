<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge;

/**
 * Thrown when a message cannot be converted.
 */
final class ConversionFailed extends \RuntimeException implements BridgeException
{
    /**
     * The message is the reason, followed by the message of the previous exception when there is one.
     *
     * @internal The bridge uses this method. It is not part of the public API.
     */
    public static function because(string $reason, ?\Throwable $previous = null): self
    {
        $message = null === $previous ? $reason : $reason.': '.$previous->getMessage();

        return new self($message, 0, $previous);
    }
}
