<?php
declare(strict_types=1);

namespace YAWAF\Core\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * NB: despite the output format being basically the same as what you from CURL, and despite the name, there is
 * no guarantee that this will print the actual over-the-wire http request/response, as building that is left to the Client...
 * @todo investigate if we can somehow fix that
 * @todo allow more flexibility in what to trace: 1st line only, also headers, also body (body can be ugly when binary)
 */
class Tracer implements MiddlewareInterface
{
    protected string $fileName;
    protected string $requestPrefix;
    protected string $responsePrefix;

    public function __construct(string $fileName, string $requestPrefix = '> ', string $responsePrefix = '< ',)
    {
        $this->fileName = $fileName;
        $this->requestPrefix = $requestPrefix;
        $this->responsePrefix = $responsePrefix;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        file_put_contents($this->fileName, $this->serializeRequest($request), FILE_APPEND);
        $response = $handler->handle($request);
        file_put_contents($this->fileName, $this->serializeResponse($response) . "--\n", FILE_APPEND);
        return $response;
    }

    protected function serializeRequest(ServerRequestInterface $request): string
    {
        $out =  $this->requestPrefix . $request->getMethod() . ' ' . $request->getRequestTarget() . ' HTTP/' . $request->getProtocolVersion() . "\n";
        foreach ($request->getHeaders() as $name => $values) {
            $out .=  $this->requestPrefix . $name . ": " . implode(", ", $values) . "\n";
        }
        $out .=  $this->requestPrefix . "\n";
        $body = (string)$request->getBody();
        if ($body !== '') {
            $out .= $body . "\n";
        }
        return $out;
    }

    protected function serializeResponse(ResponseInterface $response): string
    {
        $out =  $this->responsePrefix . 'HTTP/' . $response->getProtocolVersion() . ' ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase() . "\n";
        foreach ($response->getHeaders() as $name => $values) {
            $out .= $this->responsePrefix . $name . ": " . implode(", ", $values) . "\n";
        }
        $out .= $this->responsePrefix . "\n";
        $body = (string)$response->getBody();
        if ($body !== '') {
            $out .= $body . "\n";
        }
        return $out;
    }
}
