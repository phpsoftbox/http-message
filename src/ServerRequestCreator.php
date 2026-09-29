<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

use function array_keys;
use function in_array;
use function is_array;
use function is_string;
use function parse_url;
use function preg_match;
use function str_replace;
use function str_starts_with;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;
use function trim;
use function ucwords;

use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

final readonly class ServerRequestCreator
{
    public function __construct(
        private ServerRequestFactoryInterface $serverRequestFactory = new ServerRequestFactory(),
        private UriFactoryInterface $uriFactory = new UriFactory(),
        private StreamFactoryInterface $streamFactory = new StreamFactory(),
        private UploadedFileFactoryInterface $uploadedFileFactory = new UploadedFileFactory(),
    ) {
    }

    /**
     * @param array<string, mixed>|null $server
     * @param array<string, mixed>|null $query
     * @param array<string, mixed>|null $parsedBody
     * @param array<string, mixed>|null $cookies
     * @param array<string, mixed>|null $files
     */
    public function fromGlobals(
        ?array $server = null,
        ?array $query = null,
        ?array $parsedBody = null,
        ?array $cookies = null,
        ?array $files = null,
    ): ServerRequestInterface {
        $server     = $server ?? $_SERVER;
        $query      = $query ?? $_GET;
        $parsedBody = $parsedBody ?? ($_POST !== [] ? $_POST : null);
        $cookies    = $cookies ?? $_COOKIE;
        $files      = $files ?? $_FILES;

        $method = (string) ($server['REQUEST_METHOD'] ?? 'GET');

        $uri     = $this->createUriFromGlobals($server);
        $request = $this->serverRequestFactory->createServerRequest($method, $uri, $server);

        foreach ($this->marshalHeaders($server) as $name => $values) {
            try {
                $request = $request->withHeader($name, $values);
            } catch (InvalidArgumentException) {
                // Заголовок с недопустимым именем или значением пропускается: плохой запрос не должен давать 500.
                continue;
            }
        }

        $request = $request->withQueryParams($query)->withCookieParams($cookies);

        if ($parsedBody !== null) {
            $request = $request->withParsedBody($parsedBody);
        }

        $body    = $this->streamFactory->createStreamFromFile('php://input', 'r');
        $request = $request->withBody($body);

        if ($files !== []) {
            $request = $request->withUploadedFiles($this->normalizeFiles($files));
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $server
     */
    private function createUriFromGlobals(array $server): UriInterface
    {
        $scheme = $this->detectScheme($server);

        // Порт берётся из Host, если он пришёл: SERVER_PORT за проброшенным портом (docker, балансировщик) описывает
        // порт внутри, а не тот, к которому обращался клиент. SERVER_NAME и SERVER_PORT — запасной вариант без Host.
        $authority = $this->parseHostHeader((string) ($server['HTTP_HOST'] ?? ''));
        if ($authority === null) {
            $host      = (string) ($server['SERVER_NAME'] ?? $server['SERVER_ADDR'] ?? '');
            $port      = $this->parsePort((string) ($server['SERVER_PORT'] ?? ''));
            $authority = [$host, $port];
        }

        [$host, $port]  = $authority;
        [$path, $query] = $this->splitRequestTarget((string) ($server['REQUEST_URI'] ?? '/'));
        $query ??= (string) ($server['QUERY_STRING'] ?? '');

        $uri = $this->uriFactory->createUri()->withScheme($scheme);

        try {
            $uri = $uri->withHost($host);
        } catch (InvalidArgumentException) {
            // Некорректный host от клиента не должен превращаться в 500: оставляем URI без authority.
            $port = null;
        }

        return $uri
            ->withPort($this->normalizePort($scheme, $port))
            ->withPath($path)
            ->withQuery($query);
    }

    /**
     * Разбирает заголовок Host в host и порт. IPv6-адрес указывается в квадратных скобках (`[::1]:8080`);
     * пустой или некорректный порт (`example.com:`) отбрасывается.
     *
     * @return array{0: string, 1: int|null}|null null, если заголовок пустой или не разбирается.
     */
    private function parseHostHeader(string $header): ?array
    {
        $header = trim($header);
        if ($header === '') {
            return null;
        }

        if (str_starts_with($header, '[')) {
            $end = strpos($header, ']');
            if ($end === false) {
                return null;
            }

            $host = substr($header, 0, $end + 1);
            $rest = substr($header, $end + 1);
            if ($rest !== '' && !str_starts_with($rest, ':')) {
                return null;
            }

            return [$host, $this->parsePort(substr($rest, 1))];
        }

        $colon = strrpos($header, ':');
        if ($colon === false) {
            return [$header, null];
        }

        return [substr($header, 0, $colon), $this->parsePort(substr($header, $colon + 1))];
    }

    private function parsePort(string $port): ?int
    {
        if (preg_match('/^\d{1,5}$/', $port) !== 1) {
            return null;
        }

        $number = (int) $port;

        return $number >= 1 && $number <= 65535 ? $number : null;
    }

    /**
     * Делит request target на path и query вручную: parse_url() принимает `//evil/x` за authority и теряет часть
     * пути. Absolute-form (`http://host/path`) разбирается как URI.
     *
     * @return array{0: string, 1: string|null} query равен null, если в target нет "?".
     */
    private function splitRequestTarget(string $target): array
    {
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+\-.]*://#', $target) === 1) {
            $parts = parse_url($target);
            if ($parts !== false) {
                return [(string) ($parts['path'] ?? '/'), isset($parts['query']) ? (string) $parts['query'] : null];
            }
        }

        $fragmentPos = strpos($target, '#');
        if ($fragmentPos !== false) {
            $target = substr($target, 0, $fragmentPos);
        }

        $query    = null;
        $queryPos = strpos($target, '?');
        if ($queryPos !== false) {
            $query  = substr($target, $queryPos + 1);
            $target = substr($target, 0, $queryPos);
        }

        return [$target !== '' ? $target : '/', $query];
    }

    /**
     * @param array<string, mixed> $server
     */
    private function detectScheme(array $server): string
    {
        $https = $server['HTTPS'] ?? null;
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return 'https';
        }

        $scheme = $server['REQUEST_SCHEME'] ?? null;
        if (is_string($scheme) && $scheme !== '') {
            return strtolower($scheme);
        }

        // X-Forwarded-Proto здесь не учитывается: его подделывает клиент. За прокси схему подставляет middleware
        // доверенных прокси, только если запрос пришёл от прокси из списка.
        return 'http';
    }

    private function normalizePort(string $scheme, ?int $port): ?int
    {
        if ($port === null) {
            return null;
        }

        if ($scheme === 'https' && $port === 443) {
            return null;
        }

        if ($scheme === 'http' && $port === 80) {
            return null;
        }

        return $port;
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string[]>
     */
    private function marshalHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $name => $value) {
            if (!is_string($name)) {
                continue;
            }

            if (str_starts_with($name, 'HTTP_')) {
                $header           = $this->normalizeHeaderName(substr($name, 5));
                $headers[$header] = [(string) $value];
                continue;
            }

            if (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $header           = $this->normalizeHeaderName($name);
                $headers[$header] = [(string) $value];
            }
        }

        return $headers;
    }

    private function normalizeHeaderName(string $name): string
    {
        $name = strtolower(str_replace('_', ' ', $name));
        $name = ucwords($name);

        return str_replace(' ', '-', $name);
    }

    /**
     * @param array<string, mixed> $files
     * @return array<string, UploadedFileInterface|array>
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFileInterface) {
                $normalized[$key] = $file;
                continue;
            }

            if (is_array($file) && isset($file['tmp_name'])) {
                $normalized[$key] = $this->createUploadedFileFromSpec($file);
                continue;
            }

            if (is_array($file)) {
                $normalized[$key] = $this->normalizeFiles($file);
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function createUploadedFileFromSpec(array $spec): UploadedFileInterface|array
    {
        if (is_array($spec['tmp_name'])) {
            $files = [];
            $keys  = array_keys($spec['tmp_name']);
            foreach ($keys as $idx) {
                $files[$idx] = $this->createUploadedFileFromSpec([
                    'tmp_name' => $spec['tmp_name'][$idx] ?? '',
                    'size'     => $spec['size'][$idx] ?? null,
                    'error'    => $spec['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
                    'name'     => $spec['name'][$idx] ?? null,
                    'type'     => $spec['type'][$idx] ?? null,
                ]);
            }

            return $files;
        }

        $tmpName = (string) ($spec['tmp_name'] ?? '');
        $size    = isset($spec['size']) ? (int) $spec['size'] : null;
        $error   = isset($spec['error']) ? (int) $spec['error'] : UPLOAD_ERR_OK;
        $name    = isset($spec['name']) ? (string) $spec['name'] : null;
        $type    = isset($spec['type']) ? (string) $spec['type'] : null;

        $stream = $tmpName !== '' && $error === UPLOAD_ERR_OK
            ? $this->streamFactory->createStreamFromFile($tmpName, 'r')
            : $this->streamFactory->createStream('');

        return $this->uploadedFileFactory->createUploadedFile($stream, $size, $error, $name, $type);
    }
}
