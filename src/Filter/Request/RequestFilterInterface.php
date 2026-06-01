<?php
declare(strict_types=1);

namespace YAWAF\Core\Filter\Request;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface RequestFilterInterface
{
    /**
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface|ResponseInterface|false false when the request is black-holed and does not have to
     *         be sent to the server; a synthetic response, in which case the server is also not contacted, or the
     *         request to be sent, possibly tweaked
     */
    public function filterRequest(ServerRequestInterface $request): ServerRequestInterface|ResponseInterface|false;
}
