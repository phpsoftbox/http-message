<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use InvalidArgumentException;
use PhpSoftBox\Http\Message\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Request::class)]
#[CoversMethod(Request::class, '__construct')]
#[CoversMethod(Request::class, 'withMethod')]
#[CoversMethod(Request::class, 'withRequestTarget')]
final class RequestMethodTest extends TestCase
{
    /**
     * Проверим, что метод из конструктора сохраняется в исходном регистре: по PSR-7 он регистрозависим.
     *
     * @see Request::__construct()
     * @see Request::getMethod()
     */
    #[Test]
    public function constructorPreservesMethodCase(): void
    {
        self::assertSame('get', new Request('get', '/')->getMethod());
    }

    /**
     * Проверим, что withMethod() сохраняет метод в исходном регистре.
     *
     * @see Request::withMethod()
     */
    #[Test]
    public function withMethodPreservesMethodCase(): void
    {
        self::assertSame('Patch', new Request('GET', '/')->withMethod('Patch')->getMethod());
    }

    /**
     * Проверим, что метод с пробелом (не token) отклоняется.
     *
     * @see Request::withMethod()
     */
    #[Test]
    public function withMethodRejectsNonToken(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Request('GET', '/')->withMethod('GET /evil');
    }

    /**
     * Проверим, что request target с пробелом отклоняется: он сломал бы стартовую строку запроса.
     *
     * @see Request::withRequestTarget()
     */
    #[Test]
    public function withRequestTargetRejectsWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Request('GET', '/')->withRequestTarget("/a HTTP/1.1\r\nX: y");
    }
}
