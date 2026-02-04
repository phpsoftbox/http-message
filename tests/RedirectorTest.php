<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use PhpSoftBox\Http\Message\Redirector;
use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\Uri;
use PhpSoftBox\Router\Dispatcher;
use PhpSoftBox\Router\RouteCollector;
use PhpSoftBox\Router\Router;
use PhpSoftBox\Router\RouteResolver;
use PhpSoftBox\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_key_exists;

#[CoversClass(Redirector::class)]
#[CoversMethod(Redirector::class, 'toRoute')]
final class RedirectorTest extends TestCase
{
    /**
     * Проверяет, что для не-GET запросов используется 303.
     */
    #[Test]
    public function testToRouteUses303ForNonGetRequests(): void
    {
        $router  = $this->createRouter();
        $request = new ServerRequest('PUT', new Uri('http://example.com/users/5'));

        $redirector = new Redirector(new ResponseFactory(), $this->createSession(), $request, $router);

        $response = $redirector->toRoute('users.show', ['id' => 5])->response();

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/users/5', $response->getHeaderLine('Location'));
    }

    /**
     * Проверяет, что toRoute требует Router.
     */
    #[Test]
    public function testToRouteThrowsWithoutRouter(): void
    {
        $redirector = new Redirector(new ResponseFactory(), $this->createSession(), null, null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Router is not set for Redirector.');

        $redirector->toRoute('users.show', ['id' => 5]);
    }

    private function createRouter(): Router
    {
        $collector = new RouteCollector();

        $collector->get('/users/{id}', static fn () => null, name: 'users.show');

        $resolver   = new RouteResolver($collector);
        $dispatcher = new Dispatcher();

        return new Router($resolver, $dispatcher, $collector);
    }

    private function createSession(): SessionInterface
    {
        return new class () implements SessionInterface {
            private array $data   = [];
            private array $flash  = [];
            private bool $started = false;

            public function start(): void
            {
                $this->started = true;
            }

            public function isStarted(): bool
            {
                return $this->started;
            }

            public function all(): array
            {
                return $this->data;
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->data[$key] ?? $default;
            }

            public function set(string $key, mixed $value): void
            {
                $this->data[$key] = $value;
            }

            public function has(string $key): bool
            {
                return array_key_exists($key, $this->data);
            }

            public function forget(string $key): void
            {
                unset($this->data[$key]);
            }

            public function clear(): void
            {
                $this->data  = [];
                $this->flash = [];
            }

            public function flash(string $key, mixed $value): void
            {
                $this->flash[$key] = $value;
            }

            public function getFlash(string $key, mixed $default = null): mixed
            {
                return $this->flash[$key] ?? $default;
            }

            public function pull(string $key, mixed $default = null): mixed
            {
                if (!array_key_exists($key, $this->data)) {
                    return $default;
                }

                $value = $this->data[$key];
                unset($this->data[$key]);

                return $value;
            }

            public function save(): void
            {
            }

            public function regenerate(bool $deleteOldSession = true): void
            {
            }

            public function destroy(): void
            {
                $this->data    = [];
                $this->flash   = [];
                $this->started = false;
            }
        };
    }
}
