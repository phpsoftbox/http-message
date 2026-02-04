<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message;

use Closure;
use PhpSoftBox\Router\Router;
use PhpSoftBox\Session\SessionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function in_array;
use function strtoupper;
use function trim;

final class Redirector
{
    private ?ServerRequestInterface $request;
    private ?Closure $requestProvider;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly SessionInterface $session,
        ?ServerRequestInterface $request = null,
        private readonly ?Router $router = null,
        ?callable $requestProvider = null,
    ) {
        $this->request         = $request;
        $this->requestProvider = $requestProvider !== null
            ? Closure::fromCallable($requestProvider)
            : null;
    }

    public function setRequest(?ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function to(string $path, ?int $status = null): RedirectResponse
    {
        if ($status === null) {
            $request = $this->resolveRequest();
            if ($request !== null) {
                $status = $this->redirectStatus($request->getMethod());
            } else {
                $status = 302;
            }
        }

        $response = $this->responseFactory->createResponse($status)->withHeader('Location', $path);

        return new RedirectResponse($response, $this->session);
    }

    public function toRoute(string $name, array $params = [], ?int $status = null): RedirectResponse
    {
        if ($this->router === null) {
            throw new RuntimeException('Router is not set for Redirector.');
        }

        return $this->to($this->router->urlFor($name, $params), $status);
    }

    public function back(string $fallback = '/'): RedirectResponse
    {
        $request = $this->resolveRequest();
        if ($request === null) {
            return $this->to($fallback);
        }

        $referer = trim($request->getHeaderLine('Referer'));
        $target  = $referer !== '' ? $referer : $fallback;

        $status = $this->redirectStatus($request->getMethod());

        return $this->to($target, $status);
    }

    private function resolveRequest(): ?ServerRequestInterface
    {
        if ($this->requestProvider !== null) {
            $request = ($this->requestProvider)();
            if ($request instanceof ServerRequestInterface) {
                return $request;
            }
        }

        return $this->request;
    }

    private function redirectStatus(string $method): int
    {
        $method = strtoupper($method);

        return in_array($method, ['GET', 'HEAD'], true) ? 302 : 303;
    }
}
