<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge;

use ebln\Symfony1\Psr7Bridge\Internal\ProtocolVersion;
use ebln\Symfony1\Psr7Bridge\Internal\ServerHeaders;
use ebln\Symfony1\Psr7Bridge\Internal\UploadedFiles;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;

/**
 * Converts a symfony1 web request to a PSR-7 server request.
 *
 * The converter reads everything during convert(). Later changes to the source are not visible in the result.
 */
final class RequestConverter
{
    private const FORM_MEDIA_TYPES = ['application/x-www-form-urlencoded', 'multipart/form-data'];

    private ServerRequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private UploadedFiles $uploadedFiles;

    public function __construct(
        ServerRequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        UploadedFileFactoryInterface $fileFactory
    ) {
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->uploadedFiles = new UploadedFiles($fileFactory, $streamFactory);
    }

    /**
     * @throws ConversionFailed
     */
    public function convert(\sfWebRequest $request): ServerRequestInterface
    {
        try {
            return $this->doConvert($request);
        } catch (ConversionFailed $e) {
            throw $e;
        } catch (\Exception $e) {
            throw ConversionFailed::because('The symfony request cannot be converted', $e);
        }
    }

    private function doConvert(\sfWebRequest $request): ServerRequestInterface
    {
        $method = $request->getMethod();
        $uri = $request->getUri();
        $server = $request->getPathInfoArray();
        $content = $request->getContent();
        $get = $request->getGetParameters();
        $post = $request->getPostParameters();
        $files = $request->getFiles();

        $converted = $this->requestFactory->createServerRequest($method, $uri, $server);
        if ('' === $converted->getUri()->getHost()) {
            throw ConversionFailed::because('The symfony request has no host');
        }

        $converted = $converted->withProtocolVersion(ProtocolVersion::fromServerProtocol($server['SERVER_PROTOCOL'] ?? null));
        $headers = ServerHeaders::fromServer($server);
        foreach ($headers as $name => $value) {
            $converted = $converted->withHeader((string) $name, $value);
        }

        $body = $this->streamFactory->createStream(is_string($content) ? $content : '');
        $converted = $converted
            ->withBody($body)
            ->withQueryParams($get);

        if ($this->isFormContentType($headers['content-type'] ?? '')) {
            $converted = $converted->withParsedBody($post);
        }

        return $converted
            ->withCookieParams($_COOKIE)
            ->withUploadedFiles($this->uploadedFiles->convert($files));
    }

    private function isFormContentType(string $contentType): bool
    {
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

        return in_array($mediaType, self::FORM_MEDIA_TYPES, true);
    }
}
