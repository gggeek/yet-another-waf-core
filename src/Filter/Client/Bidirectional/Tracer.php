<?php

namespace YAWAF\Core\Filter\Client\Bidirectional;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use YAWAF\Core\Tracer\RequestTracerTrait;
use YAWAF\Core\Tracer\ResponseTracerTrait;

class Tracer implements BidirectionalFilterInterface
{
    use RequestTracerTrait;
    use ResponseTracerTrait;

    protected string $fileName;
    protected string $requestPrefix;
    protected string $responsePrefix;

    public function __construct(string $fileName, string $requestPrefix = '> ', string $responsePrefix = '< ',)
    {
        $this->fileName = $fileName;
        $this->requestPrefix = $requestPrefix;
        $this->responsePrefix = $responsePrefix;
    }

    public function filterRequest(RequestInterface $request): RequestInterface
    {
        file_put_contents($this->fileName, $this->serializeRequest($request), FILE_APPEND);
        return $request;
    }

    public function filterResponse(ResponseInterface $response, RequestInterface $request): ResponseInterface
    {
        file_put_contents($this->fileName, $this->serializeResponse($response), FILE_APPEND);
        return $response;
    }
}
