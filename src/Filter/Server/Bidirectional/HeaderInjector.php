<?php

namespace YAWAF\Core\Filter\Server\Bidirectional;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class HeaderInjector extends MiddlewareFilter
{
    protected array $overrideHeaders = [];
    protected array $overriddenHeaders = [];

    public function __construct(array $headers)
    {
        $this->overrideHeaders = $headers;
    }

    public function filterRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        $this->overriddenHeaders = [];
        foreach ($this->overrideHeaders as $name => $value) {
            if ($request->hasHeader($name)) {
                $this->overriddenHeaders[$name] = $request->getHeader($name);
            }
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    public function filterResponse(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        return $response;
    }
}
