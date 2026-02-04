<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message;

use PhpSoftBox\Session\SessionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;
use function strtoupper;
use function trim;

final class Redirector
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly SessionInterface $session,
        private readonly ?ServerRequestInterface $request = null,
    ) {
    }

    public function to(string $path, ?int $status = null): RedirectResponse
    {
        $status ??= 302;

        $response = $this->responseFactory->createResponse($status)->withHeader('Location', $path);

        return new RedirectResponse($response, $this->session);
    }

    public function back(string $fallback = '/'): RedirectResponse
    {
        if ($this->request === null) {
            return $this->to($fallback);
        }

        $referer = trim($this->request->getHeaderLine('Referer'));
        $target = $referer !== '' ? $referer : $fallback;

        $status = $this->redirectStatus($this->request->getMethod());

        return $this->to($target, $status);
    }

    private function redirectStatus(string $method): int
    {
        $method = strtoupper($method);

        return in_array($method, ['GET', 'HEAD'], true) ? 302 : 303;
    }
}
