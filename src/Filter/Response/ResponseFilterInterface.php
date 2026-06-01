<?php
declare(strict_types=1);

namespace YAWAF\Core\Filter\Response;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ResponseFilterInterface
{
    /**
     * @param ResponseInterface $response
     * @param ServerRequestInterface $request
     * @return ResponseInterface|false false when the response is black-holed and does not have to be sent to the client
     */
    public function filterResponse(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface|false;
}
