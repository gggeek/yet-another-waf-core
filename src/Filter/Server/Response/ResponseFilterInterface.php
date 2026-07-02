<?php
declare(strict_types=1);

namespace YAWAF\Core\Filter\Server\Response;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Exception\RequestDenied;

interface ResponseFilterInterface
{
    /**
     * @throws RequestDenied when the response is black-holed and does not have to be returned further back
     */
    public function filterResponse(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface;
}
