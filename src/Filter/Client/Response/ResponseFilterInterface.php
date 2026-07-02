<?php
declare(strict_types=1);

namespace YAWAF\Core\Filter\Client\Response;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use YAWAF\Core\Exception\RequestDenied;

interface ResponseFilterInterface
{
    /**
     * @throws RequestDenied when the response is black-holed and does not have to be sent to the client
     */
    public function filterResponse(ResponseInterface $response, RequestInterface $request): ResponseInterface;
}
