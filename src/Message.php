<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

use function array_key_exists;
use function array_merge;
use function array_values;
use function implode;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function preg_match;
use function strtolower;
use function trim;

abstract class Message implements MessageInterface
{
    /**
     * Метод и имя заголовка — token из RFC 7230: один или несколько tchar.
     */
    protected const string TOKEN_PATTERN = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/';

    protected string $protocolVersion = '1.1';

    /** @var array<string, string[]> */
    protected array $headers = [];

    /** @var array<string, string> */
    protected array $headerNames = [];

    protected StreamInterface $body;

    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(array $headers = [], ?StreamInterface $body = null, string $protocolVersion = '1.1')
    {
        $this->protocolVersion = $protocolVersion;
        $this->body            = $body ?? new Stream();
        $this->setHeaders($headers);
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): static
    {
        $clone                  = clone $this;
        $clone->protocolVersion = $version;

        return $clone;
    }

    /**
     * @return array<string, string[]>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    /**
     * @return string[]
     */
    public function getHeader(string $name): array
    {
        $key = $this->headerNames[strtolower($name)] ?? null;

        if ($key === null) {
            return [];
        }

        return $this->headers[$key];
    }

    public function getHeaderLine(string $name): string
    {
        $values = $this->getHeader($name);

        return implode(',', $values);
    }

    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->setHeader($name, $value, true);

        return $clone;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->setHeader($name, $value, false);

        return $clone;
    }

    public function withoutHeader(string $name): static
    {
        $clone      = clone $this;
        $normalized = strtolower($name);
        $key        = $clone->headerNames[$normalized] ?? null;
        if ($key === null) {
            return $clone;
        }

        unset($clone->headers[$key], $clone->headerNames[$normalized]);

        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): static
    {
        $clone       = clone $this;
        $clone->body = $body;

        return $clone;
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    private function setHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            $this->setHeader((string) $name, $value, true);
        }
    }

    private function setHeader(string $name, mixed $value, bool $replace): void
    {
        $normalized = $this->normalizeHeaderName($name);
        $values     = $this->normalizeHeaderValue($value);
        $key        = $this->headerNames[$normalized] ?? $name;

        if ($replace || !array_key_exists($key, $this->headers)) {
            $this->headers[$key] = $values;
        } else {
            $this->headers[$key] = array_values(array_merge($this->headers[$key], $values));
        }

        $this->headerNames[$normalized] = $key;
    }

    /**
     * Проверяет имя заголовка по грамматике token из RFC 7230 и возвращает его в нижнем регистре.
     *
     * @throws InvalidArgumentException Если имя пустое или содержит недопустимые символы.
     */
    protected function normalizeHeaderName(string $name): string
    {
        if ($name === '') {
            throw new InvalidArgumentException('Header name must not be empty.');
        }

        if (preg_match(self::TOKEN_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException('Header name contains invalid characters.');
        }

        return strtolower($name);
    }

    /**
     * Проверяет значение заголовка по грамматике field-value из RFC 7230: переводы строк, NUL и другие управляющие
     * символы, кроме горизонтальной табуляции, запрещены. Пробелы и табуляция по краям значения удаляются.
     *
     * @throws InvalidArgumentException Если значение содержит недопустимые символы.
     */
    protected function assertHeaderValue(string $value): string
    {
        if (preg_match('/^[\x09\x20-\x7E\x80-\xFF]*$/', $value) !== 1) {
            throw new InvalidArgumentException('Header value contains invalid characters.');
        }

        return trim($value, " \t");
    }

    /**
     * @return string[]
     */
    private function normalizeHeaderValue(mixed $value): array
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        if ($value === []) {
            throw new InvalidArgumentException('Header value must not be an empty array.');
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_string($item) && !is_int($item) && !is_float($item)) {
                throw new InvalidArgumentException('Header value must be a string, a number or an array of them.');
            }

            $normalized[] = $this->assertHeaderValue((string) $item);
        }

        return $normalized;
    }
}
