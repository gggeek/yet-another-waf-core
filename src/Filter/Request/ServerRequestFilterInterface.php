<?php
declare(strict_types=1);

namespace YAWAF\Core\Filter\Request;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Exception\RequestDenied;

interface ServerRequestFilterInterface
{
    /**
     * @return ServerRequestInterface|ResponseInterface either a synthetic response or the request to be sent, possibly tweaked
     * @throws RequestDenied when the request is black-holed and does not have to be sent further
     */
    public function filterServerRequest(ServerRequestInterface $request): ServerRequestInterface|ResponseInterface;
}
