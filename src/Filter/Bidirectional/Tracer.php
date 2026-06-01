<?php
declare(strict_types=1);

namespace YAWAF\Core\Filter\Bidirectional;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * NB: despite the output format being basically the same as what you from CURL, and despite the name, there is
 * no guarantee that this will print the actual http request/response, as that is left to the Client.
 *
 * @todo investigate if we can somehow fix that
 */
class Tracer implements BidirectionalFilterInterface
{
    protected $fileName;

    public function __construct(string $fileName)
    {
        $this->fileName = $fileName;
    }

    public function filterRequest(ServerRequestInterface $request): ServerRequestInterface|ResponseInterface|false
    {
        // We could serialize the request in the `filterResponse` method, bot doing it here means we will have a trace
        // of the request in case there is a fatal error before we get to trace the response
        file_put_contents($this->fileName, $this->serializeRequest($request), FILE_APPEND);
        return $request;
    }

    public function filterResponse(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface|false
    {
        file_put_contents($this->fileName, $this->serializeResponse($response) . "--\n", FILE_APPEND);
        return $response;
    }

    protected function serializeRequest(ServerRequestInterface $request): string
    {
        $out = '> ' . $request->getMethod() . ' ' . $request->getRequestTarget() . ' HTTP/' . $request->getProtocolVersion() . "\n";
        foreach ($request->getHeaders() as $name => $values) {
            $out .= '> ' . $name . ": " . implode(", ", $values) . "\n";
        }
        $out .= "> \n";
        $body = (string)$request->getBody();
        if ($body !== '') {
            $out .= $body . "\n";
        }
        return $out;
    }

    protected function serializeResponse(ResponseInterface $response): string
    {
        $out = '< ' . 'HTTP/' . $response->getProtocolVersion() . ' ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase() . "\n";
        foreach ($response->getHeaders() as $name => $values) {
            $out .= '< ' . $name . ": " . implode(", ", $values) . "\n";
        }
        $out .= "< \n";
        $body = (string)$response->getBody();
        if ($body !== '') {
            $out .= $body . "\n";
        }
        return $out;
    }
}
