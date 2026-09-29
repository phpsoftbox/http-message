<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function fopen;
use function is_resource;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

final class StreamFactory implements StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface
    {
        return new Stream($content);
    }

    /**
     * @throws InvalidArgumentException Если режим открытия недопустим.
     * @throws RuntimeException Если файл не удалось открыть.
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        if (preg_match('/^[rwaxc][bte]*\+?[bte]*$/', $mode) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid file open mode "%s".', $mode));
        }

        $error = '';
        set_error_handler(static function (int $errno, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            $resource = fopen($filename, $mode);
        } finally {
            restore_error_handler();
        }

        if ($resource === false) {
            throw new RuntimeException(sprintf('Unable to open file "%s": %s', $filename, $error));
        }

        return new Stream($resource);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        if (!is_resource($resource)) {
            throw new InvalidArgumentException('Stream resource expected.');
        }

        return new Stream($resource);
    }
}
