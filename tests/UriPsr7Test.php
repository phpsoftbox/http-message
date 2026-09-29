<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use InvalidArgumentException;
use PhpSoftBox\Http\Message\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Uri::class)]
#[CoversMethod(Uri::class, '__construct')]
#[CoversMethod(Uri::class, '__toString')]
#[CoversMethod(Uri::class, 'getPort')]
#[CoversMethod(Uri::class, 'getAuthority')]
#[CoversMethod(Uri::class, 'withPath')]
#[CoversMethod(Uri::class, 'withQuery')]
#[CoversMethod(Uri::class, 'withFragment')]
#[CoversMethod(Uri::class, 'withHost')]
#[CoversMethod(Uri::class, 'withUserInfo')]
final class UriPsr7Test extends TestCase
{
    /**
     * Проверим, что rootless path при наличии authority дополняется "/", а не склеивается с host.
     *
     * @see Uri::withPath()
     * @see Uri::__toString()
     */
    #[Test]
    public function rootlessPathWithAuthorityIsPrefixedWithSlash(): void
    {
        $uri = new Uri('http://example.com')->withPath('foo');

        self::assertSame('http://example.com/foo', (string) $uri);
    }

    /**
     * Проверим, что path без authority, начинающийся с "//", не превращается в authority при сборке строки.
     *
     * @see Uri::__toString()
     */
    #[Test]
    public function doubleSlashPathWithoutAuthorityIsReduced(): void
    {
        self::assertSame('/evil/x', (string) new Uri()->withPath('//evil/x'));
    }

    /**
     * Проверим, что недопустимые символы path кодируются.
     *
     * @see Uri::withPath()
     */
    #[Test]
    public function withPathEncodesInvalidCharacters(): void
    {
        self::assertSame('/a%20b/%3F%23', new Uri()->withPath('/a b/?#')->getPath());
    }

    /**
     * Проверим, что уже закодированные последовательности %XX в path не кодируются повторно.
     *
     * @see Uri::withPath()
     */
    #[Test]
    public function withPathDoesNotDoubleEncode(): void
    {
        self::assertSame('/a%20b/%D0%B0', new Uri()->withPath('/a%20b/%D0%B0')->getPath());
    }

    /**
     * Проверим, что в query кодируются пробел и "#", а разделители "=", "&", "?", "/" сохраняются.
     *
     * @see Uri::withQuery()
     */
    #[Test]
    public function withQueryEncodesInvalidCharacters(): void
    {
        self::assertSame('a=1%202&b=/x?y%23', new Uri()->withQuery('a=1 2&b=/x?y#')->getQuery());
    }

    /**
     * Проверим, что недопустимые символы fragment кодируются.
     *
     * @see Uri::withFragment()
     */
    #[Test]
    public function withFragmentEncodesInvalidCharacters(): void
    {
        self::assertSame('a%20b', new Uri()->withFragment('a b')->getFragment());
    }

    /**
     * Проверим, что символы URI из строки конструктора тоже кодируются.
     *
     * @see Uri::__construct()
     */
    #[Test]
    public function constructorEncodesComponents(): void
    {
        self::assertSame('https://example.com/%D0%BF%D1%83%D1%82%D1%8C?q=%D1%8F', (string) new Uri('https://example.com/путь?q=я'));
    }

    /**
     * Проверим, что стандартный порт схемы возвращается как null.
     *
     * @see Uri::getPort()
     */
    #[Test]
    public function standardPortIsReturnedAsNull(): void
    {
        self::assertNull(new Uri('https://example.com:443/')->getPort());
    }

    /**
     * Проверим, что стандартный порт не попадает в authority.
     *
     * @see Uri::getAuthority()
     */
    #[Test]
    public function standardPortIsOmittedFromAuthority(): void
    {
        self::assertSame('example.com', new Uri('http://example.com:80')->getAuthority());
    }

    /**
     * Проверим, что при смене схемы порт, ставший нестандартным, снова возвращается.
     *
     * @see Uri::getPort()
     * @see Uri::withScheme()
     */
    #[Test]
    public function portBecomesVisibleAfterSchemeChange(): void
    {
        self::assertSame(443, new Uri('https://example.com:443')->withScheme('http')->getPort());
    }

    /**
     * Проверим, что host с разделителем path отклоняется.
     *
     * @see Uri::withHost()
     */
    #[Test]
    public function withHostRejectsInvalidCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Uri()->withHost('evil.com/x');
    }

    /**
     * Проверим, что user info кодируется: ":" и "@" в логине не ломают authority.
     *
     * @see Uri::withUserInfo()
     */
    #[Test]
    public function withUserInfoEncodesDelimiters(): void
    {
        self::assertSame('us%3Aer:p%40ss', new Uri()->withUserInfo('us:er', 'p@ss')->getUserInfo());
    }
}
