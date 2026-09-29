<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use InvalidArgumentException;
use PhpSoftBox\Http\Message\StreamFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sys_get_temp_dir;

#[CoversClass(StreamFactory::class)]
#[CoversMethod(StreamFactory::class, 'createStreamFromFile')]
final class StreamFactoryFileTest extends TestCase
{
    /**
     * Проверим, что по PSR-17 для файла, который нельзя открыть, бросается RuntimeException.
     *
     * @see StreamFactory::createStreamFromFile()
     */
    #[Test]
    public function missingFileThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);

        new StreamFactory()->createStreamFromFile(sys_get_temp_dir() . '/psb-missing-dir/file.txt');
    }

    /**
     * Проверим, что по PSR-17 для недопустимого режима открытия бросается InvalidArgumentException.
     *
     * @see StreamFactory::createStreamFromFile()
     */
    #[Test]
    public function invalidModeThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StreamFactory()->createStreamFromFile('php://memory', 'z');
    }
}
