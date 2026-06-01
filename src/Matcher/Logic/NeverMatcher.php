<?php
declare(strict_types=1);

namespace YAWAF\Core\Matcher\Logic;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Matcher\Request\RequestMatcherInterface;
use YAWAF\Core\Matcher\Response\ResponseMatcherInterface;

class NeverMatcher implements RequestMatcherInterface, ResponseMatcherInterface
{

    public function matches(...$items): bool
    {
        /// @todo log a warning if passed in any items
        return false;
    }

    public function matchesRequest(ServerRequestInterface $request): bool
    {
        return false;
    }

    public function matchesResponse(ResponseInterface $response): bool
    {
        return false;
    }
}
