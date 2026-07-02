<?php
declare(strict_types=1);

namespace YAWAF\Core\Filter\Client\Request;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use YAWAF\Core\Exception\RequestDenied;

interface RequestFilterInterface
{
    /**
     * @return RequestInterface|ResponseInterface either a synthetic response or the request to be sent, possibly tweaked
     * @throws RequestDenied when the request is black-holed and does not have to be sent to the server
     */
    public function filterRequest(RequestInterface $request): RequestInterface|ResponseInterface;
}
