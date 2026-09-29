<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use PhpSoftBox\Http\Message\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Response::class)]
#[CoversMethod(Response::class, 'withStatus')]
final class ResponseReasonPhraseTest extends TestCase
{
    /**
     * Проверим, что для стандартного статуса без явной фразы подставляется reason phrase из реестра IANA.
     *
     * @see Response::withStatus()
     * @see Response::getReasonPhrase()
     */
    #[Test]
    #[DataProvider('standardStatuses')]
    public function withStatusSetsStandardReasonPhrase(int $status, string $reasonPhrase): void
    {
        self::assertSame($reasonPhrase, new Response()->withStatus($status)->getReasonPhrase());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function standardStatuses(): iterable
    {
        yield '206' => [206, 'Partial Content'];
        yield '502' => [502, 'Bad Gateway'];
        yield '504' => [504, 'Gateway Timeout'];
    }
}
