<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

use function fclose;
use function fopen;
use function fwrite;
use function is_string;
use function is_uploaded_file;
use function move_uploaded_file;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function strlen;

use const UPLOAD_ERR_EXTENSION;
use const UPLOAD_ERR_OK;

final class UploadedFile implements UploadedFileInterface
{
    private StreamInterface $stream;
    private ?int $size;
    private int $error;
    private ?string $clientFilename;
    private ?string $clientMediaType;
    private const int CHUNK_SIZE = 65_536;

    private bool $moved = false;

    public function __construct(
        StreamInterface $stream,
        ?int $size = null,
        int $error = UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null,
    ) {
        if ($error < UPLOAD_ERR_OK || $error > UPLOAD_ERR_EXTENSION) {
            throw new InvalidArgumentException('Invalid upload error status.');
        }

        $this->stream          = $stream;
        $this->size            = $size;
        $this->error           = $error;
        $this->clientFilename  = $clientFilename;
        $this->clientMediaType = $clientMediaType;
    }

    public function getStream(): StreamInterface
    {
        if ($this->moved) {
            throw new RuntimeException('Uploaded file has been moved.');
        }

        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot retrieve stream due to upload error.');
        }

        return $this->stream;
    }

    public function moveTo(string $targetPath): void
    {
        if ($targetPath === '') {
            throw new InvalidArgumentException('Target path must not be empty.');
        }

        if ($this->moved) {
            throw new RuntimeException('Uploaded file already moved.');
        }

        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot move file due to upload error.');
        }

        $source = $this->stream->getMetadata('uri');
        if (is_string($source) && $source !== '' && is_uploaded_file($source)) {
            // Файл загружен через SAPI: переносим его move_uploaded_file(), который проверяет происхождение файла.
            $this->runWithErrorCapture(
                static fn (): bool => move_uploaded_file($source, $targetPath),
                sprintf('Unable to move uploaded file to "%s"', $targetPath),
            );
            $this->stream->close();
        } else {
            $this->copyStreamTo($targetPath);
        }

        $this->moved = true;
    }

    /**
     * Копирует содержимое stream в файл и проверяет, что запись прошла полностью.
     *
     * @throws RuntimeException Если целевой файл не открылся или запись не удалась.
     */
    private function copyStreamTo(string $targetPath): void
    {
        $target = $this->runWithErrorCapture(
            static fn (): mixed => fopen($targetPath, 'wb'),
            sprintf('Unable to open target file "%s"', $targetPath),
        );

        try {
            if ($this->stream->isSeekable()) {
                $this->stream->rewind();
            }

            while (!$this->stream->eof()) {
                $chunk = $this->stream->read(self::CHUNK_SIZE);
                if ($chunk === '') {
                    continue;
                }

                $this->runWithErrorCapture(
                    static fn (): mixed => fwrite($target, $chunk) === strlen($chunk),
                    sprintf('Unable to write uploaded file to "%s"', $targetPath),
                );
            }
        } finally {
            fclose($target);
        }

        $this->stream->close();
    }

    /**
     * Выполняет файловую операцию и превращает результат false или предупреждение PHP в RuntimeException.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function runWithErrorCapture(callable $operation, string $message): mixed
    {
        $error = '';
        set_error_handler(static function (int $errno, string $warning) use (&$error): bool {
            $error = $warning;

            return true;
        });

        try {
            $result = $operation();
        } finally {
            restore_error_handler();
        }

        if ($result === false) {
            throw new RuntimeException($error !== '' ? $message . ': ' . $error : $message . '.');
        }

        return $result;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}
