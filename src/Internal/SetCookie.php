<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Internal;

/**
 * One parsed `Set-Cookie` header value.
 *
 * @internal
 */
final class SetCookie
{
    public string $name;

    public string $value;

    /** Unix timestamp; null is a session cookie */
    public ?int $expire;

    /** null is "not given" */
    public ?string $path;

    public string $domain;

    public bool $secure;

    public bool $httpOnly;

    public string $sameSite;

    public function __construct(
        string $name,
        string $value,
        ?int $expire,
        ?string $path,
        string $domain,
        bool $secure,
        bool $httpOnly,
        string $sameSite
    ) {
        $this->name = $name;
        $this->value = $value;
        $this->expire = $expire;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->sameSite = $sameSite;
    }
}
