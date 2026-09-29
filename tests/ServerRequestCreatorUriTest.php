<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use PhpSoftBox\Http\Message\ServerRequestCreator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(ServerRequestCreator::class)]
#[CoversMethod(ServerRequestCreator::class, 'fromGlobals')]
final class ServerRequestCreatorUriTest extends TestCase
{
    /**
     * Проверим, что IPv6-адрес с портом из Host разбирается в host в скобках и порт.
     *
     * @see ServerRequestCreator::fromGlobals()
     */
    #[Test]
    public function ipv6HostWithPortIsParsed(): void
    {
        $uri = $this->create(['HTTP_HOST' => '[::1]:8080'])->getUri();

        self::assertSame('http://[::1]:8080/path?y=1', (string) $uri);
    }

    /**
     * Проверим, что IPv6-адрес без порта не теряет часть адреса.
     *
     * @see ServerRequestCreator::fromGlobals()
     */
    #[Test]
    public function ipv6HostWithoutPortIsParsed(): void
    {
        $uri = $this->create(['HTTP_HOST' => '[2001:db8::1]'])->getUri();

        self::assertSame('[2001:db8::1]', $uri->getHost());
    }

    /**
     * Проверим, что Host с пустым портом ("example.com:") не даёт ошибку, а порт отбрасывается.
     *
     * @see ServerRequestCreator::fromGlobals()
     */
    #[Test]
    public function emptyPortInHostIsIgnored(): void
    {
        $uri = $this->create(['HTTP_HOST' => 'example.com:'])->getUri();

        self::assertSame('http://example.com/path?y=1', (string) $uri);
    }

    /**
     * Проверим, что при наличии Host порт берётся из него, а SERVER_PORT (порт внутри контейнера) не подставляется.
     *
     * @see ServerRequestCreator::fromGlobals()
     */
    #[Test]
    public function serverPortIsIgnoredWhenHostIsPresent(): void
    {
        $uri = $this->create(['HTTP_HOST' => 'example.com', 'SERVER_PORT' => '8080'])->getUri();

        self::assertNull($uri->getPort());
    }

    /**
     * Проверим, что некорректный Host не приводит к ошибке: URI остаётся без host.
     *
     * @see ServerRequestCreator::fromGlobals()
     */
    #[Test]
    public function invalidHostIsDropped(): void
    {
        $uri = $this->create(['HTTP_HOST' => 'evil.com/x'])->getUri();

        self::assertSame('', $uri->getHost());
    }

    /**
     * Проверим, что REQUEST_URI, начинающийся с "//", остаётся путём и не разбирается как authority.
     *
     * @see ServerRequestCreator::fromGlobals()
     */
    #[Test]
    public function requestUriStartingWithDoubleSlashStaysPath(): void
    {
        $uri = $this->create(['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '//evil/x?y=1'])->getUri();

        self::assertSame('http://example.com//evil/x?y=1', (string) $uri);
    }

    /**
     * Проверим, что заголовок с недопустимым значением пропускается, а не приводит к ошибке.
     *
     * @see ServerRequestCreator::fromGlobals()
     */
    #[Test]
    public function invalidHeaderValueIsSkipped(): void
    {
        $request = $this->create(['HTTP_HOST' => 'example.com', 'HTTP_X_BAD' => "a\x01b"]);

        self::assertFalse($request->hasHeader('X-Bad'));
    }

    /**
     * @param array<string, string> $server
     */
    private function create(array $server): ServerRequestInterface
    {
        return new ServerRequestCreator()->fromGlobals(
            $server + ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/path?y=1'],
            [],
            [],
            [],
            [],
        );
    }
}
