<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge;

use ebln\Symfony1\Psr7Bridge\Internal\ProtocolVersion;
use ebln\Symfony1\Psr7Bridge\Internal\SetCookieSerializer;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Converts a symfony1 web response to a PSR-7 response.
 *
 * The converter reads the state of the object through its public methods during convert(). It adds nothing that
 * symfony adds when it sends the response, such as the default `Content-Type`. Later changes to the source are not
 * visible in the result.
 */
final class ResponseConverter
{
    private ResponseFactoryInterface $responseFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory)
    {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * @throws ConversionFailed
     */
    public function convert(\sfWebResponse $response): ResponseInterface
    {
        try {
            return $this->doConvert($response);
        } catch (ConversionFailed $e) {
            throw $e;
        } catch (\Exception $e) {
            throw ConversionFailed::because('The symfony response cannot be converted', $e);
        }
    }

    private function doConvert(\sfWebResponse $response): ResponseInterface
    {
        // Read everything first, so that the result does not depend on what happens during the conversion.
        $code = self::integer($response->getStatusCode());
        $text = self::text($response->getStatusText());
        $options = $response->getOptions();
        $headers = $response->getHttpHeaders();
        $cookies = $response->getCookies();
        $content = self::text($response->getContent());

        $converted = $this->responseFactory
            ->createResponse($code, $text)
            ->withProtocolVersion(ProtocolVersion::fromServerProtocol($options['http_protocol'] ?? null));

        foreach ($headers as $name => $value) {
            $converted = $converted->withHeader((string) $name, self::text($value));
        }
        foreach ($cookies as $cookie) {
            if (is_array($cookie)) {
                $converted = $converted->withAddedHeader('Set-Cookie', SetCookieSerializer::serialize($cookie));
            }
        }

        return $converted->withBody($this->streamFactory->createStream($content));
    }

    /**
     * Symfony stores the status code as it got it: the int 404 and the string '404' both occur. A scalar is cast
     * to an integer as PHP casts it, so '404 ' and '404abc' give 404. Anything else, and text without a leading
     * number, gives 0, which the PSR-7 implementation accepts or rejects. is_numeric() is not used: it treats
     * '404 ' differently on PHP 7.4 and PHP 8.
     *
     * @param mixed $value the stored status code
     */
    private static function integer($value): int
    {
        return is_scalar($value) ? (int) $value : 0;
    }

    /**
     * @param mixed $value a scalar and an object with `__toString()` give their text, `null` and anything else ''
     */
    private static function text($value): string
    {
        if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return '';
    }
}
