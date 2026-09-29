<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use InvalidArgumentException;
use PhpSoftBox\Http\Message\Message;
use PhpSoftBox\Http\Message\Request;
use PhpSoftBox\Http\Message\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Message::class)]
#[CoversMethod(Message::class, 'withHeader')]
#[CoversMethod(Message::class, 'withAddedHeader')]
#[CoversMethod(Message::class, '__construct')]
final class MessageHeaderValidationTest extends TestCase
{
    /**
     * Проверим, что значение заголовка с CR/LF отклоняется: иначе в ответ можно внедрить свой заголовок.
     *
     * @see Message::withHeader()
     */
    #[Test]
    public function withHeaderRejectsValueWithLineBreak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Response()->withHeader('X-A', "a\r\nSet-Cookie: x=1");
    }

    /**
     * Проверим, что значение с переводом строки отклоняется и при добавлении значения к заголовку.
     *
     * @see Message::withAddedHeader()
     */
    #[Test]
    public function withAddedHeaderRejectsValueWithLineBreak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Response(200, ['X-A' => 'a'])->withAddedHeader('X-A', "b\nX-B: c");
    }

    /**
     * Проверим, что заголовки из конструктора проходят ту же проверку значения.
     *
     * @see Message::__construct()
     */
    #[Test]
    public function constructorRejectsValueWithLineBreak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Request('GET', 'https://example.com', ['X-A' => ['ok', "bad\r\n"]]);
    }

    /**
     * Проверим, что NUL-байт в значении заголовка отклоняется.
     *
     * @see Message::withHeader()
     */
    #[Test]
    public function withHeaderRejectsValueWithNulByte(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Response()->withHeader('X-A', "a\0b");
    }

    /**
     * Проверим, что имя заголовка с символом вне token (двоеточие) отклоняется.
     *
     * @see Message::withHeader()
     */
    #[Test]
    public function withHeaderRejectsNameOutsideToken(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Response()->withHeader('X-A: b', 'c');
    }

    /**
     * Проверим, что имя заголовка из всех допустимых tchar RFC 7230 принимается.
     *
     * @see Message::withHeader()
     */
    #[Test]
    public function withHeaderAcceptsAllTokenCharacters(): void
    {
        $name = "X!#$%&'*+-.^_`|~9";

        $response = new Response()->withHeader($name, 'value');

        self::assertSame('value', $response->getHeaderLine($name));
    }

    /**
     * Проверим, что пробелы и табуляция по краям значения удаляются, а внутри — сохраняются.
     *
     * @see Message::withHeader()
     */
    #[Test]
    public function withHeaderTrimsOptionalWhitespace(): void
    {
        $response = new Response()->withHeader('X-A', " \ta b\t ");

        self::assertSame(['a b'], $response->getHeader('X-A'));
    }

    /**
     * Проверим, что пустой массив значений отклоняется: такой заголовок нельзя отправить.
     *
     * @see Message::withHeader()
     */
    #[Test]
    public function withHeaderRejectsEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Response()->withHeader('X-A', []);
    }
}
