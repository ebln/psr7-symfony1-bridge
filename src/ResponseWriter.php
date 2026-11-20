<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge;

use Psr\Http\Message\ResponseInterface;

/**
 * Writes a PSR-7 response into an existing symfony1 web response.
 */
interface ResponseWriter
{
    /**
     * @throws ConversionFailed
     */
    public function write(ResponseInterface $source, \sfWebResponse $target): void;
}
