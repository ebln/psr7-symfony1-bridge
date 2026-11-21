<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Integration;

use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

/**
 * Replaces the external suite's detection of installed PSR-7 libraries with the factories of the test class.
 *
 * Each method keeps the behaviour of the suite's own method for the same argument type.
 */
trait BuildsWithFactories
{
    abstract protected function factories(): Factories;

    /**
     * @param mixed $uri A string or a UriInterface; the suite's method has no parameter type
     */
    protected function buildUri($uri): UriInterface
    {
        if ($uri instanceof UriInterface) {
            return $uri;
        }

        if (!is_string($uri)) {
            throw new \InvalidArgumentException('A URI must be a string or a UriInterface');
        }

        return $this->factories()->uri->createUri($uri);
    }

    /**
     * @param mixed $data A string or a stream resource; the suite's method has no parameter type
     */
    protected function buildStream($data): StreamInterface
    {
        if (is_string($data)) {
            return $this->factories()->stream->createStream($data);
        }

        if (!is_resource($data)) {
            throw new \InvalidArgumentException('Stream data must be a string or a resource');
        }

        return $this->factories()->stream->createStreamFromResource($data);
    }

    /**
     * @param mixed $data A string or a stream resource; the suite's method has no parameter type
     */
    protected function buildUploadableFile($data): UploadedFileInterface
    {
        return $this->factories()->uploadedFile->createUploadedFile($this->buildStream($data));
    }
}
