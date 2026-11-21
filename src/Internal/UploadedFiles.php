<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Internal;

use ebln\Symfony1\Psr7Bridge\ConversionFailed;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * @internal
 */
final class UploadedFiles
{
    private UploadedFileFactoryInterface $fileFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct(UploadedFileFactoryInterface $fileFactory, StreamFactoryInterface $streamFactory)
    {
        $this->fileFactory = $fileFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * @param array<array-key, mixed> $files the file tree of sfWebRequest::getFiles()
     *
     * @return array<array-key, mixed> the same tree with UploadedFileInterface leaves; a value that is neither a branch nor a leaf stays unchanged
     */
    public function convert(array $files): array
    {
        $tree = [];
        foreach ($files as $key => $node) {
            if (!is_array($node)) {
                $tree[$key] = $node;

                continue;
            }
            $tree[$key] = $this->isLeaf($node) ? $this->convertLeaf($node) : $this->convert($node);
        }

        return $tree;
    }

    /**
     * @param array<array-key, mixed> $node
     */
    private function isLeaf(array $node): bool
    {
        foreach (['tmp_name', 'error'] as $key) {
            if (array_key_exists($key, $node) && !is_array($node[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array-key, mixed> $leaf
     */
    private function convertLeaf(array $leaf): UploadedFileInterface
    {
        $error = $this->toInt($leaf['error'] ?? null) ?? \UPLOAD_ERR_OK;
        $tmpName = $this->toStringOrNull($leaf['tmp_name'] ?? null) ?? '';

        try {
            $stream = \UPLOAD_ERR_OK === $error
                ? $this->streamFactory->createStreamFromFile($tmpName, 'r')
                : $this->streamFactory->createStream('');

            return $this->fileFactory->createUploadedFile(
                $stream,
                $this->toInt($leaf['size'] ?? null),
                $error,
                $this->toStringOrNull($leaf['name'] ?? null),
                $this->toStringOrNull($leaf['type'] ?? null)
            );
        } catch (\Exception $e) {
            throw ConversionFailed::because('An uploaded file cannot be converted', $e);
        }
    }

    /**
     * @param mixed $value a value of the file array, an int, a float or a numeric string
     */
    private function toInt($value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return is_string($value) && is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param mixed $value a value of the file array, a string when present
     */
    private function toStringOrNull($value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
