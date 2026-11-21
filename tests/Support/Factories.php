<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests\Support;

use GuzzleHttp\Psr7\HttpFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

final class Factories
{
    public ResponseFactoryInterface $response;
    public ServerRequestFactoryInterface $serverRequest;
    public StreamFactoryInterface $stream;
    public UploadedFileFactoryInterface $uploadedFile;
    public UriFactoryInterface $uri;

    private function __construct(
        ResponseFactoryInterface $response,
        ServerRequestFactoryInterface $serverRequest,
        StreamFactoryInterface $stream,
        UploadedFileFactoryInterface $uploadedFile,
        UriFactoryInterface $uri
    ) {
        $this->response = $response;
        $this->serverRequest = $serverRequest;
        $this->stream = $stream;
        $this->uploadedFile = $uploadedFile;
        $this->uri = $uri;
    }

    public static function guzzle(): self
    {
        $factory = new HttpFactory();

        return new self($factory, $factory, $factory, $factory, $factory);
    }

    public static function nyholm(): self
    {
        $factory = new Psr17Factory();

        return new self($factory, $factory, $factory, $factory, $factory);
    }
}
