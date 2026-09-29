<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

use function ltrim;
use function parse_url;
use function preg_match;
use function preg_replace_callback;
use function rawurlencode;
use function str_starts_with;
use function strtolower;

final class Uri implements UriInterface
{
    /**
     * Стандартные порты схем: для них getPort() возвращает null, а порт не попадает в authority.
     */
    private const array DEFAULT_PORTS = [
        'http'  => 80,
        'https' => 443,
        'ws'    => 80,
        'wss'   => 443,
    ];

    /**
     * Символы unreserved и sub-delims из RFC 3986: в компонентах URI их не нужно кодировать.
     */
    private const string CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';
    private const string CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';

    private string $scheme   = '';
    private string $userInfo = '';
    private string $host     = '';
    private ?int $port       = null;
    private string $path     = '';
    private string $query    = '';
    private string $fragment = '';

    public function __construct(string $uri = '')
    {
        if ($uri === '') {
            return;
        }

        $parts = parse_url($uri);
        if ($parts === false) {
            throw new InvalidArgumentException('Invalid URI string.');
        }

        $this->scheme   = isset($parts['scheme']) ? $this->filterScheme($parts['scheme']) : '';
        $this->host     = isset($parts['host']) ? $this->filterHost($parts['host']) : '';
        $this->port     = isset($parts['port']) ? $this->filterPort((int) $parts['port']) : null;
        $this->path     = isset($parts['path']) ? $this->filterPath($parts['path']) : '';
        $this->query    = isset($parts['query']) ? $this->filterQueryOrFragment($parts['query']) : '';
        $this->fragment = isset($parts['fragment']) ? $this->filterQueryOrFragment($parts['fragment']) : '';
        $this->userInfo = $this->filterUserInfo($parts['user'] ?? '', $parts['pass'] ?? null);
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }

        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        $port = $this->getPort();
        if ($port !== null) {
            $authority .= ':' . $port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Возвращает null, если порт не задан или совпадает со стандартным портом схемы.
     */
    public function getPort(): ?int
    {
        if ($this->port !== null && (self::DEFAULT_PORTS[$this->scheme] ?? null) === $this->port) {
            return null;
        }

        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme(string $scheme): static
    {
        $clone         = clone $this;
        $clone->scheme = $this->filterScheme($scheme);

        return $clone;
    }

    public function withUserInfo(string $user, ?string $password = null): static
    {
        $clone           = clone $this;
        $clone->userInfo = $this->filterUserInfo($user, $password);

        return $clone;
    }

    public function withHost(string $host): static
    {
        $clone       = clone $this;
        $clone->host = $this->filterHost($host);

        return $clone;
    }

    public function withPort(?int $port): static
    {
        $clone       = clone $this;
        $clone->port = $port === null ? null : $this->filterPort($port);

        return $clone;
    }

    public function withPath(string $path): static
    {
        $clone       = clone $this;
        $clone->path = $this->filterPath($path);

        return $clone;
    }

    public function withQuery(string $query): static
    {
        $clone        = clone $this;
        $clone->query = $this->filterQueryOrFragment(ltrim($query, '?'));

        return $clone;
    }

    public function withFragment(string $fragment): static
    {
        $clone           = clone $this;
        $clone->fragment = $this->filterQueryOrFragment(ltrim($fragment, '#'));

        return $clone;
    }

    public function __toString(): string
    {
        $scheme    = $this->scheme !== '' ? $this->scheme . ':' : '';
        $authority = $this->getAuthority();
        $path      = $this->path;

        if ($authority !== '') {
            // Rootless path при наличии authority дополняется ведущим "/", иначе он склеится с host.
            if (!str_starts_with($path, '/')) {
                $path = '/' . $path;
            }
        } elseif (str_starts_with($path, '//')) {
            // Без authority path не должен начинаться с "//": иначе его начало прочитается как authority.
            $path = '/' . ltrim($path, '/');
        }

        $query    = $this->query !== '' ? '?' . $this->query : '';
        $fragment = $this->fragment !== '' ? '#' . $this->fragment : '';

        return $scheme . ($authority !== '' ? '//' . $authority : '') . $path . $query . $fragment;
    }

    private function filterScheme(string $scheme): string
    {
        if ($scheme !== '' && preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*$/', $scheme) !== 1) {
            throw new InvalidArgumentException('Invalid URI scheme.');
        }

        return strtolower($scheme);
    }

    private function filterHost(string $host): string
    {
        // Host не может содержать пробелы, управляющие символы и разделители других компонентов URI.
        if (preg_match('/[\x00-\x20\x7F\/?#@\\\\]/', $host) === 1) {
            throw new InvalidArgumentException('Invalid URI host.');
        }

        return strtolower($host);
    }

    private function filterPort(int $port): int
    {
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Invalid port number.');
        }

        return $port;
    }

    private function filterUserInfo(string $user, ?string $password): string
    {
        $pattern  = '/(?:[^%' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ']++|%(?![A-Fa-f0-9]{2}))/';
        $userInfo = $this->encode($pattern, $user);
        if ($password !== null && $password !== '') {
            $userInfo .= ':' . $this->encode($pattern, $password);
        }

        return $userInfo;
    }

    private function filterPath(string $path): string
    {
        return $this->encode(
            '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/]++|%(?![A-Fa-f0-9]{2}))/',
            $path,
        );
    }

    private function filterQueryOrFragment(string $value): string
    {
        return $this->encode(
            '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/',
            $value,
        );
    }

    /**
     * Кодирует недопустимые символы компонента, не трогая уже закодированные последовательности %XX.
     */
    private function encode(string $pattern, string $value): string
    {
        return (string) preg_replace_callback(
            $pattern,
            static fn (array $match): string => rawurlencode($match[0]),
            $value,
        );
    }
}
