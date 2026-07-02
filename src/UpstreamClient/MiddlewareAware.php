<?php

namespace YAWAF\Core\UpstreamClient;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Filter\Bidirectional\ClientBidirectionalFilterInterface;
use YAWAF\Core\Logger\PrivateLoggerTrait;

class MiddlewareAware implements UpstreamClientInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use PrivateLoggerTrait;

    protected ClientBidirectionalFilterInterface $filter;
    protected UpstreamClientInterface $upstreamClient;

    public function __construct(ClientBidirectionalFilterInterface $filter, UpstreamClientInterface $upstreamClient, LoggerInterface|null $logger = null)
    {
        $this->filter = $filter;
        $this->upstreamClient = $upstreamClient;
        $this->logger = $logger;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $request = $this->filter->filterClientRequest($request);
        if ($request instanceof ResponseInterface) {
            return $request;
        }
        $response = $this->upstreamClient->sendRequest($request);
/// @todo should we pass the original or modified request ??? (possibly cloning it)
        return $this->filter->filterResponse($response, $request);
    }

    public function withOptions(array $options): UpstreamClientInterface
    {
        $clone = clone $this;
        $clone->upstreamClient = $clone->upstreamClient->withOptions($options);
        return $clone;
    }

    public function getUserAgent(): string
    {
        return $this->upstreamClient->getUserAgent();
    }
}
