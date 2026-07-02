<?php

namespace YAWAF\Core\Filter\Server\Bidirectional;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Brings the MiddlewareInterface and BidirectionalFilterInterface under one roof
 */
abstract class MiddlewareFilter implements MiddlewareInterface, BidirectionalFilterInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $this->filterRequest($request);
        if ($request instanceof ResponseInterface) {
            return $request;
        }
        $response = $handler->handle($request);
/// @todo should we pass back the original request or the modified one ??? (possibly cloned, have to check immutability...)
        return $this->filterResponse($response, $request);
    }

    abstract public function filterRequest(ServerRequestInterface $request): ServerRequestInterface|ResponseInterface;

    abstract function filterResponse(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface;
}
