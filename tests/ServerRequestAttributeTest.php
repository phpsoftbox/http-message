<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerRequest::class)]
#[CoversMethod(ServerRequest::class, 'getAttribute')]
final class ServerRequestAttributeTest extends TestCase
{
    /**
     * Проверим, что сохранённый null возвращается как значение атрибута, а не подменяется default.
     *
     * @see ServerRequest::getAttribute()
     * @see ServerRequest::withAttribute()
     */
    #[Test]
    public function getAttributeReturnsStoredNull(): void
    {
        $request = new ServerRequest('GET', '/')->withAttribute('user', null);

        self::assertNull($request->getAttribute('user', 'default'));
    }
}
