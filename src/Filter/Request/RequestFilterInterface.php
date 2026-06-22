<?php
declare(strict_types=1);

namespace YAWAF\Core\Filter\Request;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Exception\RequestDenied;

interface RequestFilterInterface
{
    /**
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface|ResponseInterface a synthetic response, in which case the server is also not contacted, or the
     *         request to be sent, possibly tweaked
     * @throws RequestDenied when the request is black-holed and does not have to be sent to the server
     */
    public function filterRequest(ServerRequestInterface $request): ServerRequestInterface|ResponseInterface;
}
