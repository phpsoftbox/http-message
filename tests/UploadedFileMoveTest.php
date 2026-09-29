<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use PhpSoftBox\Http\Message\Stream;
use PhpSoftBox\Http\Message\UploadedFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(UploadedFile::class)]
#[CoversMethod(UploadedFile::class, 'moveTo')]
final class UploadedFileMoveTest extends TestCase
{
    /**
     * Проверим, что при невозможности записать целевой файл moveTo() бросает RuntimeException, а не молча завершается.
     *
     * @see UploadedFile::moveTo()
     */
    #[Test]
    public function unwritableTargetThrowsRuntimeException(): void
    {
        $uploaded = new UploadedFile(new Stream('payload'));

        $this->expectException(RuntimeException::class);

        $uploaded->moveTo(sys_get_temp_dir() . '/psb-missing-dir/target.txt');
    }

    /**
     * Проверим, что после неудачного переноса файл не считается перенесённым и stream остаётся доступен.
     *
     * @see UploadedFile::moveTo()
     * @see UploadedFile::getStream()
     */
    #[Test]
    public function failedMoveKeepsStreamAvailable(): void
    {
        $uploaded = new UploadedFile(new Stream('payload'));

        try {
            $uploaded->moveTo(sys_get_temp_dir() . '/psb-missing-dir/target.txt');
        } catch (RuntimeException) {
            // Ожидаемая ошибка записи.
        }

        self::assertSame('payload', (string) $uploaded->getStream());
    }

    /**
     * Проверим, что stream, прочитанный до конца, переносится целиком: чтение начинается с начала.
     *
     * @see UploadedFile::moveTo()
     */
    #[Test]
    public function readStreamIsMovedFromBeginning(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'psb');
        self::assertIsString($tmp);

        $stream = new Stream('payload');

        $stream->getContents();

        new UploadedFile($stream)->moveTo($tmp);

        self::assertSame('payload', file_get_contents($tmp));

        unlink($tmp);
    }
}
