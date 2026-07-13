<?php
declare(strict_types=1);

namespace YAWAF\Core\Filter\Bidirectional;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * NB: as per https://www.rfc-editor.org/info/rfc9110/#section-12.5.3-10.1:
 * If no Accept-Encoding header field is in the request, any content coding is considered acceptable by the user agent.
 */
class RemoveAcceptEncoding extends RequestHeaderRemover
{
    use BodyCompressorTrait;

    public function __construct()
    {
        $this->overrideHeaders = ['Accept-Encoding'];
    }

    public function filterResponse(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        if ($response->hasHeader('Content-Encoding') && isset($this->overriddenHeaders['Accept-Encoding'])) {
            $response = $this->transcodeResponseBody($response, $this->overriddenHeaders['Accept-Encoding']);

            /// @todo is 'accept-encoding' always lowercase?
            if (!$response->hasHeader('Vary') || !in_array('accept-encoding', $response->getHeader('Vary'))) {
                $response = $response->withAddedHeader('Vary', 'accept-encoding');
            }
        }

        return $response;
    }
}
