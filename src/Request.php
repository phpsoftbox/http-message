<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

use function is_string;
use function preg_match;

class Request extends Message implements RequestInterface
{
    private string $method;
    private UriInterface $uri;
    private ?string $requestTarget = null;

    /**
     * @param array<string, string|string[]> $headers
     * @param resource|string|StreamInterface|null $body
     */
    public function __construct(
        string $method,
        UriInterface|string $uri,
        array $headers = [],
        $body = null,
        string $protocolVersion = '1.1',
    ) {
        $this->method = $this->filterMethod($method);
        $this->uri    = is_string($uri) ? new Uri($uri) : $uri;

        $stream = $body instanceof StreamInterface ? $body : new Stream($body);

        parent::__construct($headers, $stream, $protocolVersion);

        if (!$this->hasHeader('Host') && $this->uri->getHost() !== '') {
            $this->setHostFromUri($this->uri);
        }
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }

        $query = $this->uri->getQuery();
        if ($query !== '') {
            $target .= '?' . $query;
        }

        return $target;
    }

    public function withRequestTarget(string $requestTarget): static
    {
        if ($requestTarget === '') {
            throw new InvalidArgumentException('Request target must not be empty.');
        }

        if (preg_match('/\s/', $requestTarget) === 1) {
            throw new InvalidArgumentException('Request target must not contain whitespace.');
        }

        $clone                = clone $this;
        $clone->requestTarget = $requestTarget;

        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): static
    {
        $clone         = clone $this;
        $clone->method = $this->filterMethod($method);

        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $clone      = clone $this;
        $clone->uri = $uri;

        if ($preserveHost) {
            if (!$this->hasHeader('Host') && $uri->getHost() !== '') {
                $clone->setHostFromUri($uri);
            }

            return $clone;
        }

        if ($uri->getHost() !== '') {
            $clone->setHostFromUri($uri);
        } else {
            $clone = $clone->withoutHeader('Host');
        }

        return $clone;
    }

    /**
     * Метод по PSR-7 регистрозависим и сохраняется как передан; допустим только token из RFC 7230.
     *
     * @throws InvalidArgumentException Если метод пустой или содержит недопустимые символы.
     */
    private function filterMethod(string $method): string
    {
        if (preg_match(self::TOKEN_PATTERN, $method) !== 1) {
            throw new InvalidArgumentException('HTTP method must be a non-empty token.');
        }

        return $method;
    }

    private function setHostFromUri(UriInterface $uri): void
    {
        $host = $uri->getHost();
        if ($host === '') {
            return;
        }

        if ($uri->getPort() !== null) {
            $host .= ':' . $uri->getPort();
        }

        $existing = $this->headerNames['host'] ?? null;
        if ($existing !== null) {
            unset($this->headers[$existing]);
        }

        $this->headers['Host']     = [$this->assertHeaderValue($host)];
        $this->headerNames['host'] = 'Host';
    }
}
