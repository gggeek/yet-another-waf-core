<?php

namespace YAWAF\Core\Filter\Bidirectional;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Used to force  to enable/disable accepting encoded (compressed) responses.
 * List of valid values: https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Accept-Encoding
 */
class ForceAcceptEncoding extends HeaderAdder
{
    public function __construct(string $acceptEncoding)
    {
        parent::__construct(['Accept-encoding' => $acceptEncoding]);
    }

    public function filterResponse(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        if ($response->hasHeader('Content-Encoding')) {
/// @todo... recode the response body using a compression which was part of the accepted ones (stored in $this->overriddenHeaders)
///          NB: the content could have been multiple-encoded, such as `deflate, gzip`
        }

        return $response;
    }
}
